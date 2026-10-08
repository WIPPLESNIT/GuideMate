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

        $this->view('profile/show', [
            'title' => 'My Profile',
            'user' => $user,
            'documents' => $documents,
        ]);
    }

    public function edit(): void
    {
        $user = Auth::user();
        // Providers (guides, rental & hotel partners) review their admin-verified
        // documents right here on Profile — the Verification menu item was removed.
        $documents = User::isProviderRole((string) ($user['role'] ?? ''))
            ? GuideDocument::forUser((int) $user['id'])
            : [];

        $this->view('profile/edit', [
            'title' => 'Edit Profile',
            'user' => $user,
            'documents' => $documents,
            'errors' => errors(),
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();

        $errors = $this->requireFields(['name']);
        if ($errors !== []) {
            flash_keep_old($errors, [], '/profile/edit');
        }

        // Optional profile photo upload (images only).
        $avatarPath = null;
        if (Upload::present($_FILES['avatar'] ?? null)) {
            try {
                $avatarPath = Upload::store($_FILES['avatar'], 'avatars/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
            } catch (RuntimeException $e) {
                flash_keep_old(['avatar' => $e->getMessage()], [], '/profile/edit');
            }
        }

        User::updateProfile($userId, [
            'name' => (string) $this->input('name', ''),
            'phone' => (string) $this->input('phone', ''),
            'location' => (string) $this->input('location', ''),
            'bio' => (string) $this->input('bio', ''),
        ]);

        if ($avatarPath !== null) {
            User::updateAvatar($userId, $avatarPath);
        }

        flash('success', 'Profile updated.');
        redirect('/profile');
    }
}
