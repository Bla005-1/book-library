let books = loadBooks()
let editingId = null

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


function renderStats() {
  const total = books.length
  const readCount = books.filter((b) => b.read).length
  const unreadCount = total - readCount
  const percent = total === 0 ? 0 : ((readCount / total) * 100).toFixed(1)

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

bookForm.addEventListener('submit', (e) => {
  e.preventDefault()

  if (!validateForm()) {
    alert('Please fill out all fields correctly.')
    return
  }

  const bookData = {
    id: editingId ?? Date.now(),
    title: inputTitle.value.trim(),
    author: inputAuthor.value.trim(),
    genre: inputGenre.value.trim(),
    pages: Number(inputPages.value),
    year: Number(inputYear.value),
    read: inputRead.checked,
  }

  if (editingId) {
    books = books.map((b) => (b.id === editingId ? bookData : b))
  } else {
    books.push(bookData)
  }

  saveBooks(books)
  renderBooks()
  renderStats()
  closeModal()
})

bookTableBody.addEventListener('click', (e) => {
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
      books = books.filter((b) => b.id !== id)
      saveBooks(books)
      renderBooks()
      renderStats()
      resetForm()
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

renderBooks()
renderStats()
