let books = []
let editingId = null
let currentPage = 1
let totalPages = 1
let totalCount = 0
const API_URL = 'http://localhost/book-library/api/books.php'
const PAGE_SIZE = 10

const bookTableBody = document.getElementById('book-table-body')
const bookForm = document.getElementById('book-form')
const formTitle = document.getElementById('form-title')
const modalOverlay = document.getElementById('modal-overlay')
const closeModalBtn = document.getElementById('close-modal')
const cancelBtn = document.getElementById('cancel-btn')
const addBookBtn = document.getElementById('add-book-btn')

const inputTitle = document.getElementById('title')
const inputAuthor = document.getElementById('author')
const inputGenre = document.getElementById('genre')
const inputPages = document.getElementById('pages')
const inputYear = document.getElementById('year')
const inputRead = document.getElementById('read')

const statsTotal = document.getElementById('stat-total')
const statsRead = document.getElementById('stat-read')
const statsUnread = document.getElementById('stat-unread')
const statsPercent = document.getElementById('stat-percent')
const prevPageBtn = document.getElementById('prev-page-btn')
const nextPageBtn = document.getElementById('next-page-btn')
const pageIndicator = document.getElementById('page-indicator')

function renderBooks() {
  bookTableBody.innerHTML = ''

  const sortedBooks = [...books].sort((a, b) => b.id - a.id)

  sortedBooks.forEach((book) => {
    const tr = document.createElement('tr')

    tr.innerHTML = `
      <td>${book.title}</td>
      <td>${book.author}</td>
      <td>${book.genre}</td>
      <td>${book.pages}</td>
      <td>${book.year}</td>
      <td>${book.read ? 'Yes' : 'No'}</td>
      <td>
        <button data-id='${book.id}' class='edit-btn'>Edit</button>
        <button data-id='${book.id}' class='delete-btn'>Delete</button>
      </td>
    `

    bookTableBody.appendChild(tr)
  })
}

function renderStats(stats) {
  const total = stats.total
  const readCount = stats.read
  const unreadCount = stats.unread
  const percent = stats.percent

  statsTotal.textContent = total
  statsRead.textContent = readCount
  statsUnread.textContent = unreadCount
  statsPercent.textContent = `${percent}%`
}

function resetForm() {
  bookForm.reset()
  editingId = null
  formTitle.textContent = 'Add Book'
}

function populateForm(book) {
  inputTitle.value = book.title
  inputAuthor.value = book.author
  inputGenre.value = book.genre
  inputPages.value = book.pages
  inputYear.value = book.year
  inputRead.checked = book.read

  editingId = book.id
  formTitle.textContent = 'Edit Book'
}

function openModal(mode = 'add') {
  formTitle.textContent = mode === 'edit' ? 'Edit Book' : 'Add Book'
  modalOverlay.classList.remove('hidden')
  document.body.classList.add('modal-open')
}

function closeModal() {
  modalOverlay.classList.add('hidden')
  document.body.classList.remove('modal-open')
  resetForm()
}

function validateForm() {
  if (!inputTitle.value.trim()) return false
  if (!inputAuthor.value.trim()) return false
  if (!inputGenre.value.trim()) return false

  const pages = Number(inputPages.value)
  if (!Number.isInteger(pages) || pages <= 0) return false

  const year = Number(inputYear.value)
  if (!Number.isInteger(year) || year < 1450 || year > new Date().getFullYear()) return false

  return true
}

function updatePaginationControls() {
  if (!pageIndicator || !prevPageBtn || !nextPageBtn) return

  pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`
  prevPageBtn.disabled = currentPage <= 1
  nextPageBtn.disabled = currentPage >= totalPages
}

function buildErrorMessage(payload, status) {
  if (!payload || typeof payload !== 'object') {
    return `Request failed (${status})`
  }

  if (payload.error && typeof payload.error === 'object') {
    const message = payload.error.message || `Request failed (${status})`
    const details = payload.error.details
    if (details && typeof details === 'object') {
      const detailLines = Object.entries(details)
        .map(([field, note]) => `${field}: ${note}`)
        .join('\n')
      return `${message}\n${detailLines}`
    }
    return message
  }

  return `Request failed (${status})`
}

async function apiRequest(url, options = {}) {
  const config = {
    method: options.method || 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.headers || {}),
    },
  }

  if (options.body !== undefined) {
    config.headers['Content-Type'] = 'application/json'
    config.body = options.body
  }

  console.log(`[API] ${config.method} ${url}`)
  const response = await fetch(url, config)
  let payload = null

  try {
    payload = await response.json()
  } catch (err) {
    payload = null
  }

  if (!response.ok || !payload || payload.success === false) {
    const message = buildErrorMessage(payload, response.status)
    throw new Error(message)
  }

  return payload
}

async function fetchPage(page) {
  const url = `${API_URL}?page=${page}`
  const payload = await apiRequest(url)
  return payload
}

async function fetchAllBooks() {
  const first = await fetchPage(1)
  const total = typeof first.total === 'number' ? first.total : first.data.length
  const pageSize = typeof first.page_size === 'number' ? first.page_size : PAGE_SIZE
  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  let all = [...first.data]

  for (let page = 2; page <= totalPages; page += 1) {
    const payload = await fetchPage(page)
    all = all.concat(payload.data)
  }

  return all
}

async function fetchStats() {
  const payload = await apiRequest(`${API_URL}?stats=1`)
  return payload.data
}

async function loadPage(page) {
  const safePage = Math.max(1, page)
  let payload = await fetchPage(safePage)
  const total = typeof payload.total === 'number' ? payload.total : payload.data.length
  const pageSize = typeof payload.page_size === 'number' ? payload.page_size : PAGE_SIZE
  const computedTotalPages = Math.max(1, Math.ceil(total / pageSize))
  const finalPage = Math.min(safePage, computedTotalPages)

  if (finalPage !== safePage) {
    payload = await fetchPage(finalPage)
  }

  totalCount = total
  totalPages = Math.max(1, Math.ceil(totalCount / pageSize))
  currentPage = finalPage
  books = payload.data
  renderBooks()
  updatePaginationControls()
}

async function refreshStats() {
  const stats = await fetchStats()
  renderStats(stats)
}

async function refreshAfterMutation(targetPage) {
  await loadPage(targetPage)
  await refreshStats()
}

bookForm.addEventListener('submit', async (e) => {
  e.preventDefault()

  if (!validateForm()) {
    alert('Please fill out all fields correctly.')
    return
  }

  const bookData = {
    title: inputTitle.value.trim(),
    author: inputAuthor.value.trim(),
    genre: inputGenre.value.trim(),
    pages: Number(inputPages.value),
    year: Number(inputYear.value),
    read: inputRead.checked,
  }

  try {
    if (editingId) {
      await apiRequest(`${API_URL}?id=${editingId}`, {
        method: 'PUT',
        body: JSON.stringify(bookData),
      })
      await refreshAfterMutation(currentPage)
    } else {
      await apiRequest(API_URL, {
        method: 'POST',
        body: JSON.stringify(bookData),
      })
      await loadPage(currentPage)
      await refreshStats()
    }

    closeModal()
  } catch (err) {
    alert(err.message)
  }
})

bookTableBody.addEventListener('click', async (e) => {
  const id = Number(e.target.dataset.id)
  if (!id) return

  if (e.target.classList.contains('edit-btn')) {
    const book = books.find((b) => b.id === id)
    if (book) {
      populateForm(book)
      openModal('edit')
    }
  }

  if (e.target.classList.contains('delete-btn')) {
    if (confirm('Are you sure you want to delete this book?')) {
      try {
        await apiRequest(`${API_URL}?id=${id}`, { method: 'DELETE' })
        await refreshAfterMutation(currentPage)
        resetForm()
      } catch (err) {
        alert(err.message)
      }
    }
  }
})

// Open modal: add book
addBookBtn.addEventListener('click', () => {
  resetForm()
  openModal('add')
})

// Close modal via buttons or overlay
closeModalBtn.addEventListener('click', closeModal)
cancelBtn.addEventListener('click', closeModal)

modalOverlay.addEventListener('click', (e) => {
  if (e.target === modalOverlay) {
    closeModal()
  }
})

if (prevPageBtn) {
  prevPageBtn.addEventListener('click', async () => {
    if (currentPage <= 1) return
    try {
      await loadPage(currentPage - 1)
    } catch (err) {
      alert(err.message)
    }
  })
}

if (nextPageBtn) {
  nextPageBtn.addEventListener('click', async () => {
    if (currentPage >= totalPages) return
    try {
      await loadPage(currentPage + 1)
    } catch (err) {
      alert(err.message)
    }
  })
}

async function init() {
  try {
    await loadPage(1)
    await refreshStats()
  } catch (err) {
    alert('Unable to load books from the server.')
    renderBooks()
    renderStats({
      total: totalCount,
      read: 0,
      unread: 0,
      percent: 0,
    })
  }
}

init()
