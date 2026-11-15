<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use App\Models\GuideDocument;
use App\Models\User;
use RuntimeException;

final class ProfileController extends Controller
{
    public function show(): void
    {
        $user = Auth::user();
        $documents = User::isProviderRole((string) ($user['role'] ?? ''))
            ? GuideDocument::forUser((int) $user['id'])
            : [];

        $errors = errors();
        $openEditModal = isset($_GET['edit']) || !empty($errors);

        $this->view('profile/show', [
            'title' => 'My Profile',
            'user' => $user,
            'documents' => $documents,
            'errors' => $errors,
            'openEditModal' => $openEditModal,
        ]);
    }

    public function edit(): void
    {
        $user = Auth::user();
        $documents = User::isProviderRole((string) ($user['role'] ?? ''))
            ? GuideDocument::forUser((int) $user['id'])
            : [];

        $this->view('profile/edit', [
            'title' => 'Edit Profile',
            'user' => $user,
            'documents' => $documents,
            'errors' => errors(),
            'openEditModal' => true,
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();
        $user = Auth::user();
        $userId = (int) Auth::id();

        $errors = $this->requireFields(['name', 'email']);
        $newEmail = trim((string) $this->input('email', ''));
        if ($newEmail !== '') {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            } elseif (User::emailExists($newEmail, $userId)) {
                $errors['email'] = 'That email address is already in use.';
            }
        }

        if ($errors !== []) {
            flash_keep_old($errors, $this->all(), '/profile?edit=1');
        }

        // Optional profile photo upload (images only).
        $avatarPath = null;
        if (Upload::present($_FILES['avatar'] ?? null)) {
            try {
                $avatarPath = Upload::store($_FILES['avatar'], 'avatars/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
            } catch (RuntimeException $e) {
                flash_keep_old(['avatar' => $e->getMessage()], $this->all(), '/profile?edit=1');
            }
        }

        User::updateProfile($userId, [
            'name' => (string) $this->input('name', ''),
            'email' => $newEmail !== '' ? $newEmail : (string) ($user['email'] ?? ''),
            'phone' => (string) $this->input('phone', ''),
            'location' => (string) $this->input('location', ''),
            'bio' => (string) $this->input('bio', ''),
        ]);

        if ($avatarPath !== null) {
            User::updateAvatar($userId, $avatarPath);
        }

        Auth::refreshUser();

        flash('success', 'Profile updated successfully.');
        redirect('/profile');
    }
}
