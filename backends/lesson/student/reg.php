<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            max-width: 1000px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }

        .nav-tabs {
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link {
            color: var(--secondary-color);
            border: none;
            padding: 10px 20px;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background-color: var(--light-bg);
        }

        .form-label {
            color: var(--secondary-color);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
        }

        .form-section {
            background-color: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-section-title {
            color: var(--secondary-color);
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        /* Loading Overlay Styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid var(--light-bg);
            border-radius: 50%;
            border-top: 5px solid var(--primary-color);
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-text {
            color: var(--secondary-color);
            font-size: 1.2rem;
            font-weight: 500;
            text-align: center;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Processing Button State */
        .btn-processing {
            position: relative;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .btn-processing .spinner-border {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Add Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
        <div class="loading-text">Processing Registration...</div>
        <div class="loading-subtext" style="color: #666; margin-top: 10px; font-size: 0.9rem;">Please do not close this window</div>
    </div>

    <div class="container">
        <!-- Important Notice for Students -->
        <div class="alert alert-warning alert-dismissible fade show" role="alert" style="margin-bottom: 20px;">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-3" style="font-size: 1.5rem; color: #856404;"></i>
                <div>
                    <strong>Dear Student!</strong> If you get "Registration failed: Invalid reference number or payment verification failed." after registering, it's a network problem. Your account has been created and you can <a href="login.php" style="text-decoration: none; color: #007bff; font-weight: bold; font-size: 1.2rem;">Login</a>.
                </div> 
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <h2 class="text-center mb-4">Student Registration</h2>
        
        <!-- Reference Number Verification Section -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Reference Number Verification</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <label for="reference_verification" class="form-label">Enter Reference Number (if you have one)</label>
                        <input type="text" class="form-control" id="reference_verification" placeholder="Enter your reference number">
                        <small class="text-muted">If you have a reference number from cash payment, enter it here to auto-fill your details</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-info" onclick="verifyReference()">Verify Reference</button>
                    </div>
                </div>
                <div id="reference_result" class="mt-3" style="display: none;"></div>
            </div>
        </div>
        
        <ul class="nav nav-tabs" id="registrationTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="morning-tab" data-bs-toggle="tab" href="#morning" role="tab">Morning Class</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="afternoon-tab" data-bs-toggle="tab" href="#afternoon" role="tab">Afternoon Class</a>
            </li>
        </ul>

        <div class="tab-content mt-3" id="registrationTabContent">
            <!-- Morning Class Form -->
            <div class="tab-pane fade show active" id="morning" role="tabpanel">
                <form id="morningForm" class="needs-validation" novalidate>
                    <div class="form-section">
                        <h3 class="form-section-title">Personal Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="morning_fullname" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="morning_fullname" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="morning_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="morning_email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="morning_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="morning_phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="morning_password" class="form-label">Password</label>
                                <div class="password-toggle">
                                    <input type="password" class="form-control" id="morning_password" required>
                                    <span class="toggle-password" onclick="togglePassword('morning_password')">👁️</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="morning_photo" class="form-label">Passport Photograph</label>
                                <input type="file" class="form-control" id="morning_photo" accept="image/*" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="morning_department" class="form-label">Department</label>
                                <select class="form-select" id="morning_department" required>
                                    <option value="">Select Department</option>
                                    <option value="sciences">Sciences</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="art">Art</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Parent Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="morning_parent_name" class="form-label">Parent Name</label>
                                <input type="text" class="form-control" id="morning_parent_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="morning_parent_phone" class="form-label">Parent Phone Number</label>
                                <input type="tel" class="form-control" id="morning_parent_phone" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="morning_address" class="form-label">Residential Address</label>
                            <textarea class="form-control" id="morning_address" rows="3" required></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Payment Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="morning_payment_type" id="morning_full_payment" value="full" checked>
                                    <label class="form-check-label" for="morning_full_payment">
                                        Full Payment (₦7,000)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="morning_payment_type" id="morning_half_payment" value="half">
                                    <label class="form-check-label" for="morning_half_payment">
                                        Half Payment (₦3,500)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="morning_reference" class="form-label">Reference Number (Optional)</label>
                                <input type="text" class="form-control" id="morning_reference" placeholder="Enter pre-generated reference number">
                                <small class="text-muted">Leave empty to pay with Paystack</small>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100" onclick="handleRegistration('morning')">Continue Registration</button>
                </form>
            </div>

            <!-- Afternoon Class Form -->
            <div class="tab-pane fade" id="afternoon" role="tabpanel">
                <form id="afternoonForm" class="needs-validation" novalidate>
                    <div class="form-section">
                        <h3 class="form-section-title">Personal Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_fullname" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="afternoon_fullname" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="afternoon_email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="afternoon_phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_password" class="form-label">Password</label>
                                <div class="password-toggle">
                                    <input type="password" class="form-control" id="afternoon_password" required>
                                    <span class="toggle-password" onclick="togglePassword('afternoon_password')">👁️</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_photo" class="form-label">Passport Photograph</label>
                                <input type="file" class="form-control" id="afternoon_photo" accept="image/*" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_department" class="form-label">Department</label>
                                <select class="form-select" id="afternoon_department" required>
                                    <option value="">Select Department</option>
                                    <option value="sciences">Sciences</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="art">Art</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_class" class="form-label">Class</label>
                                <input type="text" class="form-control" id="afternoon_class" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_school" class="form-label">School</label>
                                <input type="text" class="form-control" id="afternoon_school" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Parent Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_parent_name" class="form-label">Parent Name</label>
                                <input type="text" class="form-control" id="afternoon_parent_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_parent_phone" class="form-label">Parent Phone Number</label>
                                <input type="tel" class="form-control" id="afternoon_parent_phone" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="afternoon_address" class="form-label">Residential Address</label>
                            <textarea class="form-control" id="afternoon_address" rows="3" required></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Payment Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="afternoon_payment_type" id="afternoon_full_payment" value="full" checked>
                                    <label class="form-check-label" for="afternoon_full_payment">
                                        Full Payment (₦3,000)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="afternoon_payment_type" id="afternoon_half_payment" value="half">
                                    <label class="form-check-label" for="afternoon_half_payment">
                                        Half Payment (₦1,500)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="afternoon_reference" class="form-label">Reference Number (Optional)</label>
                                <input type="text" class="form-control" id="afternoon_reference" placeholder="Enter pre-generated reference number">
                                <small class="text-muted">Leave empty to pay with Paystack</small>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100" onclick="handleRegistration('afternoon')">Continue Registration</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function verifyReference() {
            const reference = document.getElementById('reference_verification').value.trim();
            const resultDiv = document.getElementById('reference_result');
            
            if (!reference) {
                alert('Please enter a reference number');
                return;
            }
            
            // Show loading
            resultDiv.innerHTML = '<div class="alert alert-info">Verifying reference number...</div>';
            resultDiv.style.display = 'block';
            
            const formData = new FormData();
            formData.append('reference', reference);
            
            fetch('verify_reference.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Auto-fill form with reference data
                    fillFormWithReference(data.data);
                    resultDiv.innerHTML = '<div class="alert alert-success">Reference verified successfully! Form has been auto-filled.</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="alert alert-danger">Error verifying reference: ' + error.message + '</div>';
            });
        }

        function fillFormWithReference(data) {
            // Switch to appropriate tab
            const sessionType = data.session_type;
            if (sessionType === 'morning') {
                document.getElementById('morning-tab').click();
            } else {
                document.getElementById('afternoon-tab').click();
            }
            
            // Fill form fields based on session
            const prefix = sessionType;
            
            // Fill basic information
            document.getElementById(prefix + '_fullname').value = data.fullname;
            document.getElementById(prefix + '_department').value = data.department;
            
            // Set payment type
            const paymentRadio = document.querySelector(`input[name="${prefix}_payment_type"][value="${data.payment_type}"]`);
            if (paymentRadio) {
                paymentRadio.checked = true;
            }
            
            // Fill additional fields for afternoon session
            if (sessionType === 'afternoon') {
                document.getElementById(prefix + '_class').value = data.class;
                document.getElementById(prefix + '_school').value = data.school;
            }
            
            // Set reference number in the form
            document.getElementById(prefix + '_reference').value = data.reference_number;
            
            // Update payment labels
            updatePaymentLabels(sessionType);
        }

        function updatePaymentLabels(sessionType) {
            // Find the payment type labels by looking for the label elements associated with the radio buttons
            const fullPaymentRadio = document.getElementById(sessionType + '_full_payment');
            const halfPaymentRadio = document.getElementById(sessionType + '_half_payment');
            
            if (fullPaymentRadio && halfPaymentRadio) {
                const fullPaymentLabel = fullPaymentRadio.nextElementSibling;
                const halfPaymentLabel = halfPaymentRadio.nextElementSibling;
                
                if (sessionType === 'afternoon') {
                    fullPaymentLabel.textContent = 'Full Payment (₦3,000)';
                    halfPaymentLabel.textContent = 'Half Payment (₦1,500)';
                } else {
                    fullPaymentLabel.textContent = 'Full Payment (₦7,000)';
                    halfPaymentLabel.textContent = 'Half Payment (₦ 3,500)';
                }
            }
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function validateForm(formType) {
            const form = document.getElementById(formType + 'Form');
            const photoInput = document.getElementById(formType + '_photo');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }

            // Validate file size and type
            if (photoInput.files.length > 0) {
                const file = photoInput.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    return false;
                }

                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPG, JPEG and PNG files are allowed');
                    return false;
                }
            }

            return true;
        }

        function getPaymentAmount(formType) {
            const paymentType = document.querySelector(`input[name="${formType}_payment_type"]:checked`).value;
            if (formType === 'morning') {
                return paymentType === 'full' ? 10000 : 5200;
            } else {
                return paymentType === 'full' ? 4000 : 2200;
            }
        }

        function handleRegistration(formType) {
            if (!validateForm(formType)) {
                return;
            }

            const referenceNumber = document.getElementById(formType + '_reference').value.trim();
            
            if (referenceNumber) {
                // If reference number is provided, proceed with direct registration
                processRegistration(formType, referenceNumber);
            } else {
                // Otherwise, proceed with Paystack payment
                payWithPaystack(formType);
            }
        }

        function processRegistration(formType, reference) {
            showLoading();
            
            const formData = new FormData();
            formData.append('reference', reference);
            formData.append('form_type', formType);
            formData.append('payment_type', document.querySelector(`input[name="${formType}_payment_type"]:checked`).value);
            formData.append('amount', getPaymentAmount(formType));
            
            // Add all form fields to formData
            const formElements = document.getElementById(formType + 'Form').elements;
            for (let element of formElements) {
                if (element.id) {
                    if (element.type === 'file') {
                        const file = element.files[0];
                        if (file) {
                            formData.append(element.id, file);
                        }
                    } else if (element.type === 'radio') {
                        if (element.checked) {
                            formData.append(element.name, element.value);
                        }
                    } else {
                        formData.append(element.id, element.value);
                    }
                }
            }

            // Send form data to server
            fetch('process_registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Server error occurred');
                    }).catch(() => {
                        throw new Error('Failed to process registration. Please try again.');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    alert('Registration successful!');
                    if (data.receipt_url) {
                        window.open(data.receipt_url, '_blank');
                    }
                    document.getElementById(formType + 'Form').reset();
                    window.location.href = 'login.php';
                } else {
                    throw new Error(data.message || 'Registration failed');
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                alert('Registration failed: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }

        function payWithPaystack(formType) {
            if (!validateForm(formType)) {
                return;
            }

            const amount = getPaymentAmount(formType);
            const email = document.getElementById(formType + '_email').value;
            const paymentType = document.querySelector(`input[name="${formType}_payment_type"]:checked`).value;

            // Show loading state for the button
            const submitBtn = document.querySelector(`button[onclick="handleRegistration('${formType}')"]`);
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-processing');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Initializing Payment...';

            const handler = PaystackPop.setup({
                key: '',
                email: email,
                amount: amount * 100,
                currency: 'NGN',
                ref: (formType === 'morning' ? 'MORNING' : 'AFTERNOON') + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    // Show loading overlay
                    showLoading();

                    const formData = new FormData();
                    formData.append('reference', response.reference);
                    formData.append('form_type', formType);
                    formData.append('payment_type', paymentType);
                    formData.append('amount', amount);
                    
                    // Add all form fields to formData
                    const formElements = document.getElementById(formType + 'Form').elements;
                    for (let element of formElements) {
                        if (element.id) {
                            if (element.type === 'file') {
                                const file = element.files[0];
                                if (file) {
                                    formData.append(element.id, file);
                                }
                            } else if (element.type === 'radio') {
                                if (element.checked) {
                                    formData.append(element.name, element.value);
                                }
                            } else {
                                formData.append(element.id, element.value);
                            }
                        }
                    }

                    // Send form data to server
                    fetch('process_registration.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => {
                                throw new Error(err.message || 'Server error occurred');
                            }).catch(() => {
                                throw new Error('Failed to process registration. Please try again.');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            alert('Registration successful!');
                            // Download receipt
                            if (data.receipt_url) {
                                window.open(data.receipt_url, '_blank');
                            }
                            document.getElementById(formType + 'Form').reset();
                            window.location.href = 'login.php';
                        } else {
                            throw new Error(data.message || 'Registration failed');
                        }
                    })
                    .catch(error => {
                        console.error('Error details:', error);
                        alert('Registration failed: ' + error.message);
                    })
                    .finally(() => {
                        // Hide loading overlay
                        hideLoading();
                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-processing');
                        submitBtn.innerHTML = 'Continue Registration';
                    });
                },
                onClose: function() {
                    // Reset button state if payment modal is closed
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('btn-processing');
                    submitBtn.innerHTML = 'Continue Registration';
                    alert('Transaction cancelled');
                }
            });
            handler.openIframe();
        }
    </script>
</body>
</html>
