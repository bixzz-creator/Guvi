$(document).ready(function() {
    let token = localStorage.getItem('session_token');
    
    // Fallback security: If no token exists, redirect immediately
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Configure global AJAX headers manually since common.js is gone
    $.ajaxSetup({
        beforeSend: function(xhr) {
            if (token) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + token);
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                localStorage.removeItem('session_token');
                alert("Session expired.");
                window.location.href = 'login.html';
            }
        }
    });

    // Fetch Profile Data immediately on Page Load
    fetchProfile();

    function fetchProfile() {
        $.ajax({
            url: 'php/profile.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#fullname').val(response.data.mysql.fullname);
                    $('#email').val(response.data.mysql.email);

                    let mongo = response.data.mongo;
                    if (mongo) {
                        $('#age').val(mongo.age || '');
                        $('#dob').val(mongo.dob || '');
                        $('#phone').val(mongo.phone || '');
                        $('#gender').val(mongo.gender || '');
                        $('#address').val(mongo.address || '');
                        $('#city').val(mongo.city || '');
                        $('#state').val(mongo.state || '');
                        $('#country').val(mongo.country || '');
                        $('#bio').val(mongo.bio || '');
                    }
                } else {
                    showMessage(response.message, 'danger');
                }
            }
        });
    }

    // Handle Profile Update Submission
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        $('#updateBtn').prop('disabled', true).text('Updating...');

        $.ajax({
            url: 'php/profile.php',
            type: 'POST',
            data: $(this).serialize(), // serialize all form fields
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showMessage(response.message, 'success');
                } else {
                    showMessage(response.message, 'danger');
                }
            },
            complete: function() {
                $('#updateBtn').prop('disabled', false).text('Update Profile');
            }
        });
    });

    // Logout handling
    $('#logoutBtn').on('click', function() {
        $.ajax({
            url: 'php/profile.php?action=logout',
            type: 'GET',
            success: function() {
                localStorage.removeItem('session_token');
                window.location.href = 'login.html';
            },
            error: function() {
                localStorage.removeItem('session_token');
                window.location.href = 'login.html';
            }
        });
    });

    function showMessage(msg, type) {
        let box = $('#messageBox');
        box.removeClass('d-none alert-success alert-danger').addClass('alert-' + type).text(msg);
    }
});
