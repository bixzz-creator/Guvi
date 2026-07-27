$(document).ready(function() {
    $('#registerForm').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        // Get form values
        let firstname = $('#firstname').val().trim();
        let lastname = $('#lastname').val().trim();
        let email = $('#email').val().trim();
        let password = $('#password').val();
        let confirmPassword = $('#confirm_password').val();

        // Basic Frontend Validation
        if(firstname === '' || lastname === '' || email === '' || password === '' || confirmPassword === '') {
            showMessage('All fields are required.', 'danger');
            return;
        }

        if(password !== confirmPassword) {
            showMessage('Passwords do not match.', 'danger');
            return;
        }

        if(password.length < 6) {
            showMessage('Password must be at least 6 characters long.', 'danger');
            return;
        }

        // Disable button during AJAX
        $('#registerBtn').prop('disabled', true).text('Registering...');

        // AJAX Request
        $.ajax({
            url: 'php/register.php',
            type: 'POST',
            data: {
                firstname: firstname,
                lastname: lastname,
                email: email,
                password: password
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showMessage(response.message, 'success');
                    $('#registerForm')[0].reset();
                    // Redirect to login after 2 seconds
                    setTimeout(function() {
                        window.location.href = 'login.html';
                    }, 2000);
                } else {
                    showMessage(response.message, 'danger');
                    $('#registerBtn').prop('disabled', false).text('Register');
                }
            },
            error: function() {
                showMessage('Server error. Please try again later.', 'danger');
                $('#registerBtn').prop('disabled', false).text('Register');
            }
        });
    });

    // Helper function to show messages
    function showMessage(msg, type) {
        let box = $('#messageBox');
        box.removeClass('d-none alert-success alert-danger').addClass('alert-' + type).text(msg);
    }
});
