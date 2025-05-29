<?php
require_once 'includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE MODEL COLLEGE - Learning Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .nav-pills .nav-link {
            color: var(--primary-color);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background-color: var(--secondary-color);
        }

        .content-section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .video-grid, .pdf-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .card {
            border: none;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .video-thumbnail {
            position: relative;
            padding-top: 56.25%;
            background: #000;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .video-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .play-overlay i {
            font-size: 4rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .video-thumbnail:hover .play-overlay {
            opacity: 1;
        }

        .pdf-icon {
            color: var(--accent-color);
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .search-box {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-box input {
            padding-left: 3rem;
            border-radius: 2rem;
            border: 2px solid #e9ecef;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <h1 class="display-4">Learning Dashboard</h1>
            <p class="lead">Access your educational resources</p>
        </div>
    </div>

    <div class="container">
        <ul class="nav nav-pills mb-4 justify-content-center" id="resourceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="videos-tab" data-bs-toggle="pill" data-bs-target="#videos" type="button">
                    <i class="fas fa-video me-2"></i>Video Library
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pdfs-tab" data-bs-toggle="pill" data-bs-target="#pdfs" type="button">
                    <i class="fas fa-file-pdf me-2"></i>PDF Library
                </button>
            </li>
        </ul>

        <div class="tab-content" id="resourceTabsContent">
            <!-- Videos Section -->
            <div class="tab-pane fade show active" id="videos" role="tabpanel">
                <div class="content-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="videoSearch" placeholder="Search videos...">
                    </div>
                    <?php include 'includes/videos.php'; ?>
                </div>
            </div>

            <!-- PDFs Section -->
            <div class="tab-pane fade" id="pdfs" role="tabpanel">
                <div class="content-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="pdfSearch" placeholder="Search PDFs...">
                    </div>
                    <?php include 'includes/pdfs.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('videoSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const videos = document.querySelectorAll('.video-card');
            
            videos.forEach(video => {
                const title = video.querySelector('.card-title').textContent.toLowerCase();
                const description = video.querySelector('.card-text')?.textContent.toLowerCase() || '';
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    video.style.display = '';
                } else {
                    video.style.display = 'none';
                }
            });
        });

        document.getElementById('pdfSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const pdfs = document.querySelectorAll('.pdf-card');
            
            pdfs.forEach(pdf => {
                const title = pdf.querySelector('.card-title').textContent.toLowerCase();
                const description = pdf.querySelector('.card-text')?.textContent.toLowerCase() || '';
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    pdf.style.display = '';
                } else {
                    pdf.style.display = 'none';
                }
            });
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
        });
    </script>
</body>
</html> 