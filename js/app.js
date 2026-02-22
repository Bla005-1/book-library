let books = []
let editingId = null
let currentPage = 1
let totalPages = 1
let totalCount = 0
let currentSort = 'desc'
let currentSearch = ''
let currentPageSize = 10
let searchDebounceTimer = null

const API_URL = 'api/books.php'
const PAGE_SIZE_COOKIE = 'book_lib_page_size'
const PAGE_SIZE_OPTIONS = [5, 10, 20, 50]

const bookTableBody = document.getElementById('book-table-body')
const bookForm = document.getElementById('book-form')
const formTitle = document.getElementById('form-title')
const modalOverlay = document.getElementById('modal-overlay')
const closeModalBtn = document.getElementById('close-modal')
const cancelBtn = document.getElementById('cancel-btn')
const addBookBtn = document.getElementById('add-book-btn')
const searchInput = document.getElementById('search-title')
const sortYearBtn = document.getElementById('sort-year-btn')
const pageSizeSelect = document.getElementById('page-size')

const inputTitle = document.getElementById('title')
const inputAuthor = document.getElementById('author')
const inputGenre = document.getElementById('genre')
const inputImageUrl = document.getElementById('image-url')
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

const DEFAULT_PLACEHOLDER_IMAGE = 'https://placehold.co/96x128?text=No+Image'
const INLINE_FALLBACK_PLACEHOLDER =
  'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2296%22 height=%22128%22 viewBox=%220 0 96 128%22%3E%3Crect width=%2296%22 height=%22128%22 fill=%22%23e5e7eb%22/%3E%3Crect x=%2212%22 y=%2212%22 width=%2272%22 height=%22104%22 fill=%22%23cbd5e1%22/%3E%3Cpath d=%22M12 95l22-22 17 17 13-13 20 18v21H12z%22 fill=%22%2394a3b8%22/%3E%3Ccircle cx=%2232%22 cy=%2242%22 r=%228%22 fill=%22%2394a3b8%22/%3E%3C/svg%3E'

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function normalizeImageUrl(value) {
  const url = String(value || '').trim()
  return url || DEFAULT_PLACEHOLDER_IMAGE
}

function getCookie(name) {
  const prefix = `${name}=`
  const cookies = document.cookie ? document.cookie.split(';') : []

  for (const rawCookie of cookies) {
    const cookie = rawCookie.trim()
    if (cookie.startsWith(prefix)) {
      return decodeURIComponent(cookie.slice(prefix.length))
    }
  }

  return null
}

function setCookie(name, value, days = 365) {
  const maxAge = days * 24 * 60 * 60
  document.cookie = `${name}=${encodeURIComponent(String(value))}; path=/; max-age=${maxAge}; samesite=lax`
}

function readSavedPageSize() {
  const raw = getCookie(PAGE_SIZE_COOKIE)
  if (!raw) return 10

  const parsed = Number(raw)
  if (!Number.isInteger(parsed) || !PAGE_SIZE_OPTIONS.includes(parsed)) {
    return 10
  }

  return parsed
}

function updateSortButtonLabel() {
  if (!sortYearBtn) return

  if (currentSort === 'asc') {
    sortYearBtn.textContent = 'Oldest to Newest'
    sortYearBtn.setAttribute('aria-pressed', 'true')
  } else {
    sortYearBtn.textContent = 'Newest to Oldest'
    sortYearBtn.setAttribute('aria-pressed', 'false')
  }
}

function buildListUrl(page) {
  const params = new URLSearchParams()
  params.set('page', String(page))
  params.set('page_size', String(currentPageSize))
  params.set('sort', currentSort)

  if (currentSearch.trim() !== '') {
    params.set('search', currentSearch.trim())
  }

  return `${API_URL}?${params.toString()}`
}

function renderBooks() {
  bookTableBody.innerHTML = ''

  books.forEach((book) => {
    const tr = document.createElement('tr')
    const imageUrl = normalizeImageUrl(book.image_url)
    const title = escapeHtml(book.title)
    const author = escapeHtml(book.author)
    const genre = escapeHtml(book.genre)

    tr.innerHTML = `
      <td>
        <img
          src="${escapeHtml(imageUrl)}"
          alt="Cover for ${title}"
          class="book-thumb"
          loading="lazy"
          onerror="if (this.dataset.fallback !== '1') { this.dataset.fallback = '1'; this.src='${INLINE_FALLBACK_PLACEHOLDER}'; }"
        />
      </td>
      <td>${title}</td>
      <td>${author}</td>
      <td>${genre}</td>
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
  inputImageUrl.value = DEFAULT_PLACEHOLDER_IMAGE
  editingId = null
  formTitle.textContent = 'Add Book'
}

function populateForm(book) {
  inputTitle.value = book.title
  inputAuthor.value = book.author
  inputGenre.value = book.genre
  inputImageUrl.value = normalizeImageUrl(book.image_url)
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
  if (!inputImageUrl.value.trim()) return false

  const pages = Number(inputPages.value)
  if (!Number.isInteger(pages) || pages <= 0) return false

  const year = Number(inputYear.value)
  if (!Number.isInteger(year) || year < 1450 || year > new Date().getFullYear()) return false

  return true
}

function updatePaginationControls() {
  if (!pageIndicator || !prevPageBtn || !nextPageBtn) return

  pageIndicator.textContent = `Page ${currentPage} of ${totalPages} (${totalCount} total)`
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

  let response = null
  try {
    response = await fetch(url, config)
  } catch (err) {
    throw new Error('Network error: unable to reach the server. Please check your connection and try again.')
  }

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
  const url = buildListUrl(page)
  const payload = await apiRequest(url)
  return payload
}

async function fetchStats() {
  const payload = await apiRequest(`${API_URL}?stats=1`)
  return payload.data
}

async function loadPage(page) {
  const safePage = Math.max(1, page)
  let payload = await fetchPage(safePage)

  const total = typeof payload.total === 'number' ? payload.total : payload.data.length
  const pageSize = typeof payload.page_size === 'number' ? payload.page_size : currentPageSize
  const computedTotalPages = Math.max(1, Math.ceil(total / pageSize))
  const finalPage = Math.min(safePage, computedTotalPages)

  if (finalPage !== safePage) {
    payload = await fetchPage(finalPage)
  }

  totalCount = total
  currentPageSize = pageSize
  totalPages = Math.max(1, Math.ceil(totalCount / currentPageSize))
  currentPage = finalPage
  books = Array.isArray(payload.data) ? payload.data : []

  renderBooks()
  updatePaginationControls()
}

async function refreshStats() {
  const stats = await fetchStats()
  renderStats(stats)
}

async function refreshAfterMutation(targetPage = 1) {
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
    image_url: normalizeImageUrl(inputImageUrl.value),
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
      await refreshAfterMutation(1)
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

addBookBtn.addEventListener('click', () => {
  resetForm()
  openModal('add')
})

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

if (sortYearBtn) {
  sortYearBtn.addEventListener('click', async () => {
    currentSort = currentSort === 'desc' ? 'asc' : 'desc'
    updateSortButtonLabel()

    try {
      await loadPage(1)
    } catch (err) {
      alert(err.message)
    }
  })
}

if (searchInput) {
  searchInput.addEventListener('input', () => {
    const nextSearch = searchInput.value.trim()

    if (searchDebounceTimer !== null) {
      window.clearTimeout(searchDebounceTimer)
    }

    searchDebounceTimer = window.setTimeout(async () => {
      currentSearch = nextSearch

      try {
        await loadPage(1)
      } catch (err) {
        alert(err.message)
      }
    }, 300)
  })
}

if (pageSizeSelect) {
  pageSizeSelect.addEventListener('change', async () => {
    const selected = Number(pageSizeSelect.value)
    if (!Number.isInteger(selected) || !PAGE_SIZE_OPTIONS.includes(selected)) {
      alert('Invalid page size selected.')
      pageSizeSelect.value = String(currentPageSize)
      return
    }

    currentPageSize = selected
    setCookie(PAGE_SIZE_COOKIE, currentPageSize)

    try {
      await loadPage(1)
    } catch (err) {
      alert(err.message)
    }
  })
}

async function init() {
  currentPageSize = readSavedPageSize()

  if (pageSizeSelect) {
    pageSizeSelect.value = String(currentPageSize)
  }

  updateSortButtonLabel()

  try {
    await loadPage(1)
    await refreshStats()
  } catch (err) {
    alert(`Unable to load books from the server. ${err.message}`)
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
