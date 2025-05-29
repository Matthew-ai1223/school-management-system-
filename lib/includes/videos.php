<?php
require_once 'config.php';

// Get all videos ordered by creation date
$sql = "SELECT * FROM videos ORDER BY created_at DESC";
$result = $conn->query($sql);

// Load YouTube IFrame API
echo '<script src="https://www.youtube.com/iframe_api"></script>';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Video Library</h2>
        <button class="btn btn-primary" onclick="showVideoUploadModal()">
            <i class="fas fa-plus"></i> Add Video
        </button>
    </div>

    <div class="video-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($video = $result->fetch_assoc()): ?>
                <div class="video-card">
                    <div class="video-thumbnail" id="video-<?php echo $video['id']; ?>">
                        <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                             alt="<?php echo htmlspecialchars($video['title']); ?>"
                             class="w-100 h-100 object-fit-cover"
                             style="cursor: pointer;"
                             onclick="openVideoPlayer(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title']); ?>')">
                        <div class="play-overlay">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                    <div class="p-3">
                        <h5 class="card-title"><?php echo htmlspecialchars($video['title']); ?></h5>
                        <?php if ($video['description']): ?>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($video['description']); ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">
                                Added <?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                            </small>
                            <button class="btn btn-sm btn-primary" onclick="openVideoPlayer(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title']); ?>')">
                                <i class="fas fa-play"></i> Watch Video
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="display-6 text-muted mb-4">No videos yet</div>
                <p class="lead">Start by adding your first YouTube video to the library.</p>
                <button class="btn btn-lg btn-primary mt-3" onclick="showVideoUploadModal()">
                    <i class="fas fa-plus"></i> Add Video
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Player Modal -->
<div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="videoPlayerTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="video-container">
                    <div id="videoContainer"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.video-thumbnail {
    position: relative;
    padding-top: 56.25%; /* 16:9 Aspect Ratio */
    background: #000;
    overflow: hidden;
}

.video-thumbnail img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
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

.video-thumbnail:hover img {
    transform: scale(1.05);
}

.video-thumbnail:hover .play-overlay {
    opacity: 1;
}

#videoPlayerModal .modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
}

#videoPlayerModal .modal-header {
    padding: 1rem;
    background: rgba(0, 0, 0, 0.8);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

#videoPlayerModal .video-container {
    position: relative;
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 480px;
    background: #000;
}

#videoContainer {
    width: 100%;
    max-width: 1280px;
    aspect-ratio: 16/9;
}

#videoContainer iframe {
    width: 100%;
    height: 100%;
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.3);
}
</style>

<script>
// Function to open video player
function openVideoPlayer(videoId, title) {
    const modal = document.getElementById('videoPlayerModal');
    const videoContainer = document.getElementById('videoContainer');
    const titleElement = document.getElementById('videoPlayerTitle');
    
    // Set the title
    titleElement.textContent = title;
    
    // Create and set the iframe with the exact format
    videoContainer.innerHTML = `<iframe
        width="1280"
        height="720"
        src="includes/IFrame.php?id=${videoId}"
        title="${title}"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen>
    </iframe>`;
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Handle modal close
    modal.addEventListener('hidden.bs.modal', function () {
        videoContainer.innerHTML = ''; // Clear the video container
    }, { once: true });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
});
</script> 