<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

session_start();

if (!isset($_SESSION['student_id']) || !isset($_SESSION['exam_attempt'])) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Verify student exists
$stmt = $db->prepare("SELECT * FROM ace_school_system.students WHERE id = :student_id");
$stmt->execute([':student_id' => $_SESSION['student_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php?error=not_found');
    exit();
}

$attempt = $_SESSION['exam_attempt'];
$questions = $attempt['questions'];
$current_time = new DateTime();
$end_time = new DateTime($attempt['end_time']);
$time_remaining = $current_time->diff($end_time);

if ($current_time > $end_time) {
    header('Location: submit-exam.php?timeout=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #f3f4f6;
            --accent-color: #3b82f6;
            --text-color: #1f2937;
            --light-bg: #ffffff;
        }

        body {
            background-color: #f8fafc;
            min-height: 100vh;
            color: var(--text-color);
            padding: 20px 0;
        }

        .timer-bar {
            background: var(--primary-color);
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timer-bar i {
            font-size: 1.2rem;
        }

        .progress-text {
            text-align: right;
            margin-left: auto;
            opacity: 0.9;
        }

        .questions-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .question-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb;
            background: white;
            color: var(--text-color);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .question-number:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .question-number.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .question-number.answered {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }

        .question-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .question-text {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 25px;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .option-item {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .option-item:hover {
            border-color: var(--accent-color);
        }

        .option-item.selected {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .nav-btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .prev-btn {
            background: #e5e7eb;
            color: var(--text-color);
        }

        .next-btn {
            background: var(--primary-color);
            color: white;
        }

        .prev-btn:hover {
            background: #d1d5db;
        }

        .next-btn:hover {
            background: #1e40af;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .timer-bar {
                padding: 10px 15px;
            }

            .question-number {
                width: 35px;
                height: 35px;
            }

            .question-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="timer-bar">
            <i class='bx bx-time'></i>
            <span class="fw-bold">Time Remaining: <span id="timer">29:07</span></span>
            <span class="progress-text">Progress: 0%</span>
                    </div>

        <div class="questions-nav">
            <?php 
            // Track initially answered questions from session if they exist
            $answered_questions = isset($_SESSION['answered_questions']) ? $_SESSION['answered_questions'] : [];
            for($i = 1; $i <= count($questions); $i++): 
                $is_answered = in_array($i, $answered_questions);
            ?>
                <div class="question-number <?php echo $i === 1 ? 'active' : ''; ?> <?php echo $is_answered ? 'answered' : ''; ?>" 
                     data-question="<?php echo $i; ?>"
                     onclick="navigateToQuestion(<?php echo $i; ?>)">
                    <?php echo $i; ?>
                </div>
            <?php endfor; ?>
            </div>
            
                <form id="examForm" action="submit-exam.php" method="POST">
                    <?php foreach ($questions as $index => $question): ?>
            <div class="question-card <?php echo $index === 0 ? 'd-block' : 'd-none'; ?>" 
                 id="question-<?php echo $index + 1; ?>">
                <div class="question-text">
                    <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
                        </div>

                            <?php if ($question['image_url']): ?>
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars($question['image_url']); ?>" 
                         class="img-fluid" 
                         alt="Question Image"
                         onerror="this.onerror=null; this.src='assets/images/no-image.png';">
                </div>
                            <?php endif; ?>
                            
                <div class="options-list">
                                <?php
                                $options = [
                                    'A' => $question['option_a'],
                                    'B' => $question['option_b'],
                                    'C' => $question['option_c'],
                                    'D' => $question['option_d']
                                ];
                                foreach ($options as $key => $value):
                                ?>
                    <div class="option-item" 
                         onclick="selectOption(this, '<?php echo $question['id']; ?>', '<?php echo $key; ?>')">
                        <input type="radio" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="<?php echo $key; ?>" 
                               id="q<?php echo $question['id']; ?>-<?php echo $key; ?>"
                               style="display: none;">
                                        <?php echo $key . ') ' . htmlspecialchars($value); ?>
                    </div>
                    <?php endforeach; ?>
            </div>
            
                <div class="navigation-buttons">
                    <?php if($index > 0): ?>
                    <button type="button" class="nav-btn prev-btn" 
                            onclick="showQuestion(<?php echo $index; ?>)">
                        Previous
                    </button>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>

                    <?php if($index < count($questions) - 1): ?>
                    <button type="button" class="nav-btn next-btn" 
                            onclick="showQuestion(<?php echo $index + 2; ?>)">
                        Next
                    </button>
                    <?php else: ?>
                    <button type="submit" class="nav-btn next-btn">Submit Exam</button>
                    <?php endif; ?>
                </div>
        </div>
            <?php endforeach; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer functionality
        const serverEndTime = <?php echo strtotime($attempt['end_time']) * 1000; ?>;
        const nowServerTime = <?php echo time() * 1000; ?>;
        let remainingTime = serverEndTime - nowServerTime;

        function updateTimer() {
            if (remainingTime <= 0) {
                document.getElementById('examForm').submit();
                return;
            }

            const minutes = Math.floor(remainingTime / (1000 * 60));
            const seconds = Math.floor((remainingTime % (1000 * 60)) / 1000);

            document.getElementById('timer').innerHTML = 
                minutes.toString().padStart(2, '0') + ':' + 
                seconds.toString().padStart(2, '0');

            // Update progress
            const totalTime = serverEndTime - nowServerTime;
            const progress = Math.round(((totalTime - remainingTime) / totalTime) * 100);
            document.querySelector('.progress-text').textContent = `Progress: ${progress}%`;

            remainingTime -= 1000;
        }

        setInterval(updateTimer, 1000);
        updateTimer();

        // Navigation functions
        function showQuestion(number) {
            document.querySelectorAll('.question-card').forEach(card => {
                card.classList.add('d-none');
            });
            document.getElementById('question-' + number).classList.remove('d-none');
            
            document.querySelectorAll('.question-number').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.question-number[data-question="${number}"]`).classList.add('active');
        }

        function navigateToQuestion(number) {
            showQuestion(number);
        }

        function updateProgress() {
            const totalQuestions = <?php echo count($questions); ?>;
            const answeredQuestions = document.querySelectorAll('.question-number.answered').length;
            const progress = Math.round((answeredQuestions / totalQuestions) * 100);
            document.querySelector('.progress-text').textContent = `Progress: ${progress}%`;
        }

        function selectOption(element, questionId, option) {
            // Remove selected class from all options in this question
            element.closest('.options-list').querySelectorAll('.option-item').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio input
            document.getElementById('q' + questionId + '-' + option).checked = true;
            
            // Get the current question number from the question card
            const questionCard = element.closest('.question-card');
            const questionNumber = questionCard.id.split('-')[1];
            
            // Mark question number as answered
            const questionBtn = document.querySelector(`.question-number[data-question="${questionNumber}"]`);
            questionBtn.classList.add('answered');

            // Update progress
            updateProgress();

            // Save answered state to session via AJAX
            fetch('save-progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    question_number: questionNumber,
                    attempt_id: '<?php echo $attempt['attempt_id']; ?>',
                    answer: option
                })
            });
        }

        // Initialize progress on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
        });

        // Load previously answered questions if they exist
        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const questionId = radio.name.match(/\d+/)[0];
            const option = radio.closest('.option-item');
            if (option) {
                option.classList.add('selected');
                const questionBtn = document.querySelector(`.question-number[data-question="${questionId}"]`);
                if (questionBtn) {
                    questionBtn.classList.add('answered');
                }
            }
        });

        // Anti-cheating: Detect tab switching
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                fetch('log-activity.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        type: 'tab_switch',
                        attempt_id: '<?php echo $attempt['attempt_id']; ?>',
                        student_id: '<?php echo $_SESSION['student_id']; ?>'
                    })
                });
            }
        });
    </script>
</body>
</html> 