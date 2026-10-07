<?php
declare(strict_types=1);

if (!hash_equals(csrf_token(), (string)($_GET['token'] ?? ''))) {
    flash('Sign-out link expired — click it again from the menu.', 'warning');
    redirect('/');
}

auth_logout();
redirect('/');
