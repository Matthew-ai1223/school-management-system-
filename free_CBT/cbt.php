<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE College CBT System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #0d47a1;
            --accent-color: #2962ff;
            --success-color: #4CAF50;
            --error-color: #f44336;
            --warning-color: #ff9800;
            --text-color: #333;
            --light-bg: #f5f8ff;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 1rem;
        }

        #cbt-form {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(41, 98, 255, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            width: 100%;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover:not(:disabled) {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        #test-container {
            display: none;
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .question-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .question-navigation button {
            min-width: 120px;
        }

        .timer-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .timer-display .time {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .timer-display.warning {
            background: linear-gradient(135deg, var(--warning-color), #ff5722);
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .progress-container {
            margin: 2rem 0;
            position: relative;
        }

        .progress-label {
            position: absolute;
            right: 0;
            top: -1.5rem;
            font-weight: 500;
            color: var(--primary-color);
        }

        .progress-bar {
            transition: width 0.5s ease;
        }

        #question-container {
            margin-bottom: 2rem;
        }

        #question-text {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }

        .options-list {
            list-style: none;
        }

        .option-item {
            margin-bottom: 1rem;
        }

        .option-label {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-label:hover {
            background-color: var(--light-bg);
            border-color: var(--accent-color);
        }

        .option-input {
            margin-right: 1rem;
            width: 20px;
            height: 20px;
        }

        #result-container {
            display: none;
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .result-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .result-score {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 1rem 0;
        }

        .solution-item {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--accent-color);
        }

        .solution-item.correct {
            border-left-color: var(--success-color);
        }

        .solution-item.incorrect {
            border-left-color: var(--error-color);
        }

        .solution-item h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .solution {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .question-nav-panel {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 8px;
        }

        .question-nav-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            background: var(--white);
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .question-nav-btn:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .question-nav-btn.answered {
            background: var(--success-color);
            border-color: var(--success-color);
            color: var(--white);
        }

        .question-nav-btn.current {
            background: var(--primary-color);
            color: var(--white);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .timer-display {
                top: 1rem;
                right: 1rem;
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../images/logo.png" alt="ACE College Logo" class="logo">
            <h1>ACE College CBT System</h1>
            <p>Computer Based Testing Platform</p>
        </div>

        <form id="cbt-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <select id="subject" class="form-control" required>
                        <option value="">Select Subject</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="topic">Topic</label>
                    <select id="topic" class="form-control" required>
                        <option value="">Select Topic</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="time">Set Time (minutes)</label>
                    <input type="number" id="time" class="form-control" min="1" max="180" value="30" required>
                </div>

                <div class="form-group">
                    <label for="num-questions">Number of Questions</label>
                    <input type="number" id="num-questions" class="form-control" min="1" required>
                </div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary" disabled>
                <i class="fas fa-play"></i> Start Test
            </button>
        </form>

        <div id="test-container" style="display: none;">
            <div class="timer-display">
                <div>
                    <i class="fas fa-clock"></i>
                    <span>Time Remaining:</span>
                </div>
                <div class="time" id="timer">00:00</div>
            </div>

            <div class="progress-container">
                <div class="progress-label">Progress: <span id="progress-percentage">0%</span></div>
                <div class="progress-bar"></div>
            </div>

            <div class="question-nav-panel" id="question-nav-panel">
                <!-- Question navigation buttons will be added here dynamically -->
            </div>

            <div id="question-container">
                <h3 id="question-text"></h3>
                <div id="options-container" class="options-list"></div>
            </div>

            <div class="question-navigation">
                <button id="prev-btn" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button id="next-btn" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button id="submit-test-btn" class="btn btn-primary" style="display: none;">
                    <i class="fas fa-check"></i> Submit Test
                </button>
            </div>
        </div>

        <div id="result-container" style="display: none;"></div>
    </div>

    <script src="script.js"></script>
</body>
</html>