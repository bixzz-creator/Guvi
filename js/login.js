$(document).ready(function() {
    // If already logged in (token exists), redirect to profile automatically
    if (localStorage.getItem('session_token')) {
        window.location.href = 'profile.html';
    }

    $('#loginForm').on('submit', function(e) {
        e.preventDefault();

        let email = $('#email').val().trim();
        let password = $('#password').val();

        if(email === '' || password === '') {
            showMessage('Please enter email and password.', 'danger');
            return;
        }

        $('#loginBtn').prop('disabled', true).text('Logging in...');

        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            data: {
                email: email,
                password: password
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // Store the token in LocalStorage
                    localStorage.setItem('session_token', response.token);
                    
                    showMessage('Login successful! Redirecting...', 'success');
                    
                    setTimeout(function() {
                        window.location.href = 'profile.html';
                    }, 1500);
                } else {
                    showMessage(response.message, 'danger');
                    $('#loginBtn').prop('disabled', false).text('Login');
                }
            },
            error: function() {
                showMessage('Server error. Please try again later.', 'danger');
                $('#loginBtn').prop('disabled', false).text('Login');
            }
        });
    });

    function showMessage(msg, type) {
        let box = $('#messageBox');
        box.removeClass('d-none alert-success alert-danger').addClass('alert-' + type).text(msg);
    }
});
