<?php
require_once 'config.php';

// Get all PDFs ordered by creation date
$sql = "SELECT * FROM pdfs ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>PDF Library</h2>
        <button class="btn btn-primary" onclick="showPDFUploadModal()">
            <i class="fas fa-plus"></i> Upload PDF
        </button>
    </div>

    <div class="pdf-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($pdf = $result->fetch_assoc()): ?>
                <div class="pdf-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="pdf-icon me-3">
                                    <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($pdf['title']); ?></h5>
                                    <small class="text-muted">
                                        <?php echo format_file_size($pdf['file_size']); ?> • 
                                        <?php echo $pdf['downloads']; ?> downloads
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($pdf['description']): ?>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($pdf['description']); ?></p>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="openPDFViewer('<?php echo htmlspecialchars($pdf['file_path']); ?>')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                
                                <a href="includes/download_pdf.php?id=<?php echo $pdf['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                Added <?php echo date('M j, Y', strtotime($pdf['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="display-6 text-muted mb-4">No PDFs yet</div>
                <p class="lead">Start by uploading your first PDF to the library.</p>
                <button class="btn btn-lg btn-primary mt-3" onclick="showPDFUploadModal()">
                    <i class="fas fa-plus"></i> Upload PDF
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
});
</script> 