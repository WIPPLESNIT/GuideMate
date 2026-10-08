<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Core\Upload;

final class ReviewController extends Controller
{
    public function store(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            abort(404, 'Listing not found.');
        }

        $userId = (int) Auth::id();
        $back = '/listing/' . $listing['slug'];

        if (!Booking::hasCompletedBooking((int) $listing['id'], $userId)) {
            flash('error', 'You can only review experiences you have booked.');
            redirect($back);
        }
        if (Review::userHasReviewed((int) $listing['id'], $userId)) {
            flash('error', 'You have already reviewed this listing.');
            redirect($back);
        }

        $rating = (int) $this->input('rating', 0);
        $comment = (string) $this->input('comment', '');
        $title = (string) $this->input('title', '');

        $errors = [];
        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = 'Please select a rating from 1 to 5 stars.';
        }
        if (trim($comment) === '') {
            $errors['comment'] = 'Please write a short review.';
        }
        if ($errors !== []) {
            flash_keep_old($errors, [], $back);
        }

        $reviewId = Review::create((int) $listing['id'], $userId, $rating, $title, $comment, date('Y-m-d'));
        if (!empty($_FILES['photo']['name'])) {
            $path = Upload::image($_FILES['photo'], 'reviews');
            if ($path !== null) {
                ReviewImage::add($reviewId, $path);
            }
        }
        flash('success', 'Thanks for sharing your experience!');
        redirect($back);
    }
}
