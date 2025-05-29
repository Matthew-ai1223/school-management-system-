// Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar toggle
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            content.classList.toggle('active');
        });
    }

    // Initialize modals
    const videoUploadModal = new bootstrap.Modal(document.getElementById('videoUploadModal'));
    const pdfUploadModal = new bootstrap.Modal(document.getElementById('pdfUploadModal'));

    // Handle video upload form submission
    const videoUploadForm = document.getElementById('videoUploadForm');
    if (videoUploadForm) {
        videoUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            uploadVideo(formData);
        });
    }

    // Handle PDF upload form submission
    const pdfUploadForm = document.getElementById('pdfUploadForm');
    if (pdfUploadForm) {
        pdfUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            uploadPDF(formData);
        });
    }

    // Load initial content
    loadContent('home');
});

// Show video upload modal
function showVideoUploadModal() {
    const modal = new bootstrap.Modal(document.getElementById('videoUploadModal'));
    modal.show();
}

// Show PDF upload modal
function showPDFUploadModal() {
    const modal = new bootstrap.Modal(document.getElementById('pdfUploadModal'));
    modal.show();
}

// Load content based on section
function loadContent(section) {
    showSpinner();
    
    // API endpoints for different sections
    const endpoints = {
        'home': 'includes/home.php',
        'videos': 'includes/videos.php',
        'pdfs': 'includes/pdfs.php',
        'downloads': 'includes/downloads.php'
    };

    fetch(endpoints[section])
        .then(response => response.text())
        .then(html => {
            document.getElementById('dashboard-content').innerHTML = html;
            hideSpinner();
        })
        .catch(error => {
            console.error('Error loading content:', error);
            hideSpinner();
            showError('Failed to load content. Please try again.');
        });
}

// Upload video
function uploadVideo(formData) {
    showSpinner();
    
    fetch('includes/upload_video.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Video added successfully!');
            document.getElementById('videoUploadForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('videoUploadModal')).hide();
            loadContent('videos');
        } else {
            showError(data.message || 'Failed to add video. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error uploading video:', error);
        showError('Failed to upload video. Please try again.');
    })
    .finally(() => {
        hideSpinner();
    });
}

// Upload PDF
function uploadPDF(formData) {
    showSpinner();
    
    fetch('includes/upload_pdf.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('PDF uploaded successfully!');
            document.getElementById('pdfUploadForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('pdfUploadModal')).hide();
            loadContent('pdfs');
        } else {
            showError(data.message || 'Failed to upload PDF. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error uploading PDF:', error);
        showError('Failed to upload PDF. Please try again.');
    })
    .finally(() => {
        hideSpinner();
    });
}

// Show loading spinner
function showSpinner() {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    document.body.appendChild(spinner);
}

// Hide loading spinner
function hideSpinner() {
    const spinner = document.querySelector('.spinner-overlay');
    if (spinner) {
        spinner.remove();
    }
}

// Show success message
function showSuccess(message) {
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

// Show error message
function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-danger border-0 position-fixed top-0 end-0 m-3';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

// Initialize YouTube Player API
function initYouTubePlayer(videoId, containerId) {
    new YT.Player(containerId, {
        height: '100%',
        width: '100%',
        videoId: videoId,
        playerVars: {
            'playsinline': 1,
            'rel': 0
        }
    });
}

// Handle PDF viewer
function openPDFViewer(pdfUrl) {
    const viewer = document.createElement('div');
    viewer.className = 'modal fade';
    viewer.id = 'pdfViewerModal';
    viewer.setAttribute('tabindex', '-1');
    
    viewer.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe src="${pdfUrl}" class="pdf-viewer"></iframe>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(viewer);
    const modal = new bootstrap.Modal(viewer);
    modal.show();
    
    viewer.addEventListener('hidden.bs.modal', () => viewer.remove());
} 