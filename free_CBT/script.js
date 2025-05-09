document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const form = document.getElementById('cbt-form');
    const subjectSelect = document.getElementById('subject');
    const topicSelect = document.getElementById('topic');
    const timeInput = document.getElementById('time');
    const numQuestionsInput = document.getElementById('num-questions');
    const submitBtn = document.getElementById('submit-btn');
    const testContainer = document.getElementById('test-container');
    const questionContainer = document.getElementById('question-container');
    const questionText = document.getElementById('question-text');
    const optionsContainer = document.getElementById('options-container');
    const timerDisplay = document.getElementById('timer');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitTestBtn = document.getElementById('submit-test-btn');
    const resultContainer = document.getElementById('result-container');
    const progressBar = document.querySelector('.progress-bar');
    const questionNavPanel = document.getElementById('question-nav-panel');
    const progressPercentage = document.getElementById('progress-percentage');

    // Global state
    let currentQuestions = [];
    let currentQuestionIndex = 0;
    let timeLeft;
    let timer;
    let userAnswers = {};
    let questionBank = {};
    let warningThreshold = 300; // 5 minutes warning

    // Subject-to-topic mapping
    const subjectTopics = {
        Mathematics: [],
        English: [],
        Chemistry: [],
        Biology: [],
        Financial_Accounting: []
    };

    // Initialize the application
    async function initializeApp() {
        try {
            await loadSubjectsAndTopics();
            setupEventListeners();
            validateForm();
        } catch (error) {
            console.error('Error initializing app:', error);
            alert('Error loading questions. Please refresh the page.');
        }
    }

    function setupEventListeners() {
        subjectSelect.addEventListener('change', updateTopics);
        form.addEventListener('input', validateForm);
        form.addEventListener('submit', startTest);
        prevBtn.addEventListener('click', showPreviousQuestion);
        nextBtn.addEventListener('click', showNextQuestion);
        submitTestBtn.addEventListener('click', submitTest);
        optionsContainer.addEventListener('change', handleOptionSelection);
    }

    async function loadSubjectsAndTopics() {
        try {
            const subjectFiles = {
                'Mathematics': 'math.json',
                'English': 'english.json',
                'Chemistry': 'chemistry.json',
                'Biology': 'biology.json',
                'Financial_Accounting': 'financial_accounting.json'
            };

            for (const [subject, filename] of Object.entries(subjectFiles)) {
                try {
                    const response = await fetch(`question/${filename}`);
                    if (response.ok) {
                        const data = await response.json();
                        // Get topics from the JSON structure
                        const topics = Object.keys(data);
                        subjectTopics[subject] = topics;
                        questionBank[subject] = data;
                    }
                } catch (error) {
                    console.error(`Error loading ${subject}:`, error);
                }
            }
            populateSubjects();
        } catch (error) {
            console.error('Error loading subjects and topics:', error);
            throw error;
        }
    }

    function populateSubjects() {
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        Object.keys(subjectTopics).forEach(subject => {
            const option = document.createElement('option');
            option.value = subject;
            option.textContent = subject;
            subjectSelect.appendChild(option);
        });
    }

    function updateTopics() {
        const subject = subjectSelect.value;
        topicSelect.innerHTML = '<option value="">Select Topic</option>';
        
        if (subject && subjectTopics[subject]) {
            subjectTopics[subject].forEach(topic => {
                const option = document.createElement('option');
                option.value = topic;
                option.textContent = topic;
                topicSelect.appendChild(option);
            });
        }
        validateForm();
    }

    function validateForm() {
        const isValid = 
            subjectSelect.value &&
            topicSelect.value &&
            timeInput.value > 0 &&
            numQuestionsInput.value > 0;
        submitBtn.disabled = !isValid;
    }

    function startTest(e) {
        e.preventDefault();
        const subject = subjectSelect.value;
        const topic = topicSelect.value;
        const numQuestions = parseInt(numQuestionsInput.value);
        
        if (questionBank[subject] && questionBank[subject][topic]) {
            const allQuestions = questionBank[subject][topic].questions;
            currentQuestions = shuffleArray(allQuestions).slice(0, numQuestions);
            currentQuestionIndex = 0;
            userAnswers = {};
            
            // Hide form and show test
            form.style.display = 'none';
            testContainer.style.display = 'block';
            
            // Create question navigation buttons
            createQuestionNavigation(numQuestions);
            
            // Start timer
            timeLeft = parseInt(timeInput.value) * 60;
            startTimer();
            
            // Show first question
            showQuestion(0);
            updateProgress();
        }
    }

    function createQuestionNavigation(numQuestions) {
        questionNavPanel.innerHTML = '';
        for (let i = 0; i < numQuestions; i++) {
            const btn = document.createElement('button');
            btn.className = 'question-nav-btn';
            btn.textContent = i + 1;
            btn.addEventListener('click', () => showQuestion(i));
            questionNavPanel.appendChild(btn);
        }
        updateQuestionNavigation();
    }

    function updateQuestionNavigation() {
        const buttons = questionNavPanel.getElementsByClassName('question-nav-btn');
        for (let i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('current', 'answered');
            if (i === currentQuestionIndex) {
                buttons[i].classList.add('current');
            }
            if (userAnswers[i] !== undefined) {
                buttons[i].classList.add('answered');
            }
        }
    }

    function startTimer() {
        updateTimerDisplay();
        timer = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            
            // Add warning class when 5 minutes remaining
            if (timeLeft === warningThreshold) {
                timerDisplay.parentElement.classList.add('warning');
                alert('5 minutes remaining!');
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                alert('Time is up!');
                submitTest();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    function showQuestion(index) {
        currentQuestionIndex = index;
        const question = currentQuestions[index];
        questionText.textContent = `${index + 1}. ${question.question}`;
        
        optionsContainer.innerHTML = question.options.map((option, i) => `
            <div class="option-item">
                <label class="option-label">
                    <input type="radio" 
                           name="question${index}" 
                           value="${i}"
                           ${userAnswers[index] === i ? 'checked' : ''}>
                    ${option}
                </label>
            </div>
        `).join('');

        // Update navigation buttons
        prevBtn.disabled = index === 0;
        nextBtn.style.display = index === currentQuestions.length - 1 ? 'none' : 'block';
        submitTestBtn.style.display = index === currentQuestions.length - 1 ? 'block' : 'none';
        
        updateQuestionNavigation();
        updateProgress();
    }

    function handleOptionSelection(e) {
        if (e.target.type === 'radio') {
            userAnswers[currentQuestionIndex] = parseInt(e.target.value);
            updateProgress();
            updateQuestionNavigation();
        }
    }

    function showPreviousQuestion() {
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            showQuestion(currentQuestionIndex);
        }
    }

    function showNextQuestion() {
        if (currentQuestionIndex < currentQuestions.length - 1) {
            currentQuestionIndex++;
            showQuestion(currentQuestionIndex);
        }
    }

    function updateProgress() {
        const progress = (Object.keys(userAnswers).length / currentQuestions.length) * 100;
        progressBar.style.width = `${progress}%`;
        progressPercentage.textContent = `${Math.round(progress)}%`;
    }

    function submitTest() {
        clearInterval(timer);
        timerDisplay.parentElement.classList.remove('warning');
        
        let score = 0;
        const results = currentQuestions.map((question, index) => {
            const isCorrect = userAnswers[index] === question.correctAnswer;
            if (isCorrect) score++;
            
            return {
                question: question.question,
                userAnswer: userAnswers[index] !== undefined ? question.options[userAnswers[index]] : 'Not answered',
                correctAnswer: question.options[question.correctAnswer],
                isCorrect,
                explanation: question.solution || 'No explanation provided'
            };
        });

        const percentage = (score / currentQuestions.length) * 100;
        
        resultContainer.innerHTML = `
            <div class="result-header">
                <h2>Test Results</h2>
                <div class="result-score">${percentage.toFixed(1)}%</div>
                <p>You got ${score} out of ${currentQuestions.length} questions correct</p>
            </div>
            <div class="solutions">
                ${results.map((result, index) => `
                    <div class="solution-item ${result.isCorrect ? 'correct' : 'incorrect'}">
                        <p><strong>Question ${index + 1}:</strong> ${result.question}</p>
                        <p><strong>Your Answer:</strong> ${result.userAnswer}</p>
                        <p><strong>Correct Answer:</strong> ${result.correctAnswer}</p>
                        <p><strong>Explanation:</strong> ${result.explanation}</p>
                    </div>
                `).join('')}
            </div>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-redo"></i> Take Another Test
            </button>
        `;

        testContainer.style.display = 'none';
        resultContainer.style.display = 'block';
    }

    function shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    // Initialize the application
    initializeApp();
});

// lokan8298@gmail.com