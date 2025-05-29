<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>Learning Dashboard</h3>
            </div>
            <ul class="list-unstyled components">
                <li class="active">
                    <a href="#"><i class="fas fa-home"></i> Home</a>
                </li>
                <li>
                    <a href="#videoSubmenu" data-bs-toggle="collapse" aria-expanded="false">
                        <i class="fab fa-youtube"></i> Videos
                    </a>
                    <ul class="collapse list-unstyled" id="videoSubmenu">
                        <li><a href="#" onclick="loadContent('videos')">All Videos</a></li>
                        <li><a href="#" onclick="showVideoUploadModal()">Upload Video</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#pdfSubmenu" data-bs-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-file-pdf"></i> PDFs
                    </a>
                    <ul class="collapse list-unstyled" id="pdfSubmenu">
                        <li><a href="#" onclick="loadContent('pdfs')">All PDFs</a></li>
                        <li><a href="#" onclick="showPDFUploadModal()">Upload PDF</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#" onclick="loadContent('downloads')">
                        <i class="fas fa-download"></i> Downloads
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div id="content" class="content">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="d-flex">
                        <div class="search-box">
                            <input type="text" class="form-control" placeholder="Search...">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                        <div class="user-profile ms-3">
                            <img src="assets/img/default-avatar.png" alt="User" class="rounded-circle">
                            <span class="ms-2">Admin</span>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <div id="dashboard-content" class="container-fluid py-4">
                <!-- Content will be loaded dynamically here -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Videos</h5>
                                <p class="card-text display-4">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total PDFs</h5>
                                <p class="card-text display-4">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Downloads</h5>
                                <p class="card-text display-4">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Video Upload Modal -->
    <div class="modal fade" id="videoUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add YouTube Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="videoUploadForm">
                        <div class="mb-3">
                            <label class="form-label">Video Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">YouTube URL</label>
                            <input type="url" class="form-control" name="youtube_url" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Video</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Upload Modal -->
    <div class="modal fade" id="pdfUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="pdfUploadForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">PDF Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PDF File</label>
                            <input type="file" class="form-control" name="pdf_file" accept=".pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload PDF</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html> 