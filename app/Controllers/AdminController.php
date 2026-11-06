<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AdminAuth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\ListingSchedule;
use App\Models\AccountAppeal;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Feedback;
use App\Models\GuideDocument;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationService;

final class AdminController extends Controller
{
    public function index(): void
    {
        Booking::reconcileOrphanRefunds();
        $this->view('dashboard/admin', [
            'title' => 'Admin Dashboard',
            'stats' => [
                'tourists' => User::countByRole('tourist'),
                'guides' => User::countByRole('guide'),
                'listings' => Listing::count('approved'),
                'pending' => Listing::count('pending'),
                'pendingGuides' => User::countProvidersByStatus('pending'),
                'pendingVehicles' => Vehicle::pendingCount(),
                'openDisputes' => Dispute::countOpen(),
                'revenue' => Payment::totalRevenue(),
            ],
            'pending' => Listing::pending(),
            'pendingGuides' => User::providers('pending'),
            'recentReviews' => Review::recent(6),
        ], 'admin');
    }

    public function feedback(): void
    {
        $this->view('admin/feedback', [
            'title' => 'App Feedback',
            'items' => Feedback::all(),
        ], 'admin');
    }

    public function updateFeedbackStatus(string $id): void
    {
        $this->verifyCsrf();
        $status = (string) $this->input('status', '');
        if (!in_array($status, Feedback::STATUSES, true)) {
            flash('error', 'Invalid feedback status.');
            redirect('/admin/feedback');
        }
        Feedback::markStatus((int) $id, $status);
        AuditLog::recordAction('feedback.status', 'app_feedback', (int) $id, ['status' => $status]);
        flash('success', 'Feedback updated.');
        redirect('/admin/feedback');
    }

    public function guides(): void
    {
        // Only applications awaiting review are listed. Once approved (or
        // rejected) a provider drops off this queue. Approved providers are
        // managed from the Users page (where they can be revoked). This queue
        // covers all provider roles: guides, rental partners and hotel partners.
        $guides = User::providers('pending');
        $documents = [];
        foreach ($guides as $g) {
            $documents[(int) $g['id']] = GuideDocument::forUser((int) $g['id']);
        }
        $this->view('admin/guides', [
            'title' => 'Partner Applications',
            'guides' => $guides,
            'documents' => $documents,
        ], 'admin');
    }

    public function approveGuide(string $id): void
    {
        $this->verifyCsrf();
        $guide = User::find((int) $id);
        $role = (string) ($guide['role'] ?? '');
        if ($guide === null || !User::isProviderRole($role)) {
            flash('error', 'This application could not be approved.');
            redirect('/admin/guides');
        }

        User::setGuideStatus((int) $id, 'approved');
        AuditLog::recordAction('guide.approve', 'user', (int) $id);
        $label = User::PROVIDER_LABELS[$role] ?? 'partner';
        NotificationService::guideApproved($guide['email'], $guide['name'], $label);
        $this->notifyPartnerDecision((int) $id, true);
        flash('success', 'Application Approved');
        redirect('/admin/guides');
    }

    public function rejectGuide(string $id): void
    {
        $this->verifyCsrf();
        $guide = User::find((int) $id);
        $role = (string) ($guide['role'] ?? '');
        if ($guide === null || !User::isProviderRole($role)) {
            flash('error', 'This application could not be rejected.');
            redirect('/admin/guides');
        }

        $note = trim((string) $this->input('note', ''));
        User::setGuideStatus((int) $id, 'rejected', $note !== '' ? $note : null);
        AuditLog::recordAction('guide.reject', 'user', (int) $id, ['note' => $note]);
        $label = User::PROVIDER_LABELS[$role] ?? 'partner';
        NotificationService::guideRejected($guide['email'], $guide['name'], $note, $label);
        $this->notifyPartnerDecision((int) $id, false, $note);
        flash('info', 'Application Rejected');
        redirect('/admin/guides');
    }

    /**
     * Revoke a previously approved partner (from the Users page). They lose the
     * ability to publish and must re-submit documents to be reviewed again.
     */
    public function revokeGuide(string $id): void
    {
        $this->verifyCsrf();
        $guide = User::find((int) $id);
        $role = (string) ($guide['role'] ?? '');
        if ($guide !== null && User::isProviderRole($role)) {
            $label = User::PROVIDER_LABELS[$role] ?? 'partner';
            User::setGuideStatus((int) $id, 'rejected', 'Your ' . strtolower($label) . ' verification was revoked by an administrator.');
            AuditLog::recordAction('guide.revoke', 'user', (int) $id);
            flash('info', $guide['name'] . '\'s verification has been revoked.');
        }
        $return = trim((string) $this->input('return', ''));
        if ($return === '') {
            $return = '/admin/users/' . (int) $id . '/edit';
        }
        redirect($return);
    }

    public function listings(): void
    {
        $category = (string) $this->input('category', 'all');
        $q = (string) $this->input('q', '');

        $this->view('admin/listings', [
            'title' => 'Manage Listings',
            'listings' => Listing::allLiveForAdmin($category, $q),
            'category' => $category,
            'q' => $q,
            'categoryCounts' => Listing::countLiveByCategory(),
            'pendingGuideCount' => Listing::pendingGuideListingsCount(),
            'pendingHotelsCount' => Listing::pendingHotelsCount(),
        ], 'admin');
    }

    public function guideListings(): void
    {
        $status = (string) $this->input('status', 'all');
        $category = (string) $this->input('category', 'all');

        $this->view('admin/guide-listings', [
            'title' => 'Guide Listings Review',
            'listings' => Listing::allGuideListingsForAdmin($status, $category),
            'status' => $status,
            'category' => $category,
            'pendingCount' => Listing::pendingGuideListingsCount(),
        ], 'admin');
    }

    public function showGuideListing(string $id): void
    {
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            abort(404, 'Guide listing not found.');
        }

        $guide = User::find((int) $listing['user_id']);
        $reviewer = !empty($listing['reviewed_by']) ? User::find((int) $listing['reviewed_by']) : null;
        $schedules = Database::all(
            'SELECT * FROM listing_schedules WHERE listing_id = ? ORDER BY schedule_date ASC, start_time ASC',
            [(int) $listing['id']]
        );

        $isPackage = ($listing['price_unit'] ?? '') === 'package'
            || ($listing['category_slug'] ?? '') === 'packages'
            || ($listing['category_slug'] ?? '') === 'travel-packages'
            || stripos($listing['title'] ?? '', 'package') !== false
            || stripos($listing['title'] ?? '', 'expedition') !== false;

        $packageData = $isPackage ? $this->buildPackageInspectionData($listing, $guide) : null;
        $packageInclusions = $packageData ? $packageData['inclusions'] : [];

        $this->view('admin/guide-listing-show', [
            'isPackage' => $isPackage,
            'packageData' => $packageData,
            'packageInclusions' => $packageInclusions,
            'title' => ($isPackage ? 'Inspect Travel Package: ' : 'Review Guide Listing: ') . $listing['title'],
            'listing' => $listing,
            'guide' => $guide,
            'reviewer' => $reviewer,
            'schedules' => $schedules,
            'gallery' => Listing::gallery((int) $listing['id']),
        ], 'admin');
    }

    public function approveGuideListing(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            flash('error', 'Listing not found.');
            redirect('/admin/guide-listings');
        }

        $adminId = (int) AdminAuth::id();
        Listing::approveGuideListing((int) $id, $adminId);

        AuditLog::recordAction('guide_listing.approve', 'listing', (int) $id, [
            'title' => $listing['title'],
            'guide_id' => $listing['user_id'],
        ]);

        $guide = User::find((int) $listing['user_id']);
        if ($guide !== null && !empty($guide['email'])) {
            NotificationService::guideListingApproved((string) $guide['email'], (string) $listing['title']);
        }

        flash('success', "Guide listing '{$listing['title']}' has been approved and published.");
        redirect('/admin/guide-listings');
    }

    public function rejectGuideListing(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            flash('error', 'Listing not found.');
            redirect('/admin/guide-listings');
        }

        $reason = trim((string) $this->input('reason', ''));
        $adminId = (int) AdminAuth::id();
        Listing::rejectGuideListing((int) $id, $adminId, $reason !== '' ? $reason : null);

        AuditLog::recordAction('guide_listing.reject', 'listing', (int) $id, [
            'title' => $listing['title'],
            'guide_id' => $listing['user_id'],
            'reason' => $reason,
        ]);

        $guide = User::find((int) $listing['user_id']);
        if ($guide !== null && !empty($guide['email'])) {
            NotificationService::guideListingRejected((string) $guide['email'], (string) $listing['title'], $reason);
        }

        flash('info', "Guide listing '{$listing['title']}' has been rejected.");
        redirect('/admin/guide-listings');
    }

    /**
     * Resolve the rich database records for all services bundled in a travel package:
     * - Things to Do
     * - Attractions
     * - Hotel Accommodation
     * - Rental Services
     *
     * @param array<string, mixed> $listing
     * @return array<string, mixed>
     */
    private function resolvePackageInclusions(array $listing): array
    {
        $pkgData = $this->buildPackageInspectionData($listing, null);
        return $pkgData['inclusions'] ?? [];
    }

    /**
     * Comprehensive package inspection data builder for administrator review.
     * Extracts and normalizes all fields matching the customer app reference screens:
     * - Overview (Title, destination, duration, capacity, highlight badge, assurance, description, tags)
     * - Host Information (Name, DOT accreditation, tour stats, rating, bio quote)
     * - All-In-One Inclusions (Activities, Attractions, Accommodation, Rentals, Included Perks)
     * - What's Not Included (Transparent exclusions)
     * - Day-by-Day Itinerary (Tabs, schedule timeline, and highlights)
     * - Accommodation Details (Room specs, amenities pills, check-in / check-out times)
     * - Rental Vehicle Details (Model, specs, features, handover / return times)
     * - Policies & Cancellation Requirements
     *
     * @param array<string, mixed> $listing
     * @param array<string, mixed>|null $guide
     * @return array<string, mixed>
     */
    public static function buildPackageInspectionData(array $listing, ?array $guide = null): array
    {
        $id = (int) ($listing['id'] ?? 0);
        $title = (string) ($listing['title'] ?? 'Travel Package');
        $rawDesc = (string) ($listing['description'] ?? '');
        $cleanDesc = $rawDesc;
        $includedRaw = (string) ($listing['included'] ?? '');
        $notIncludedRaw = (string) ($listing['not_included'] ?? '');

        // 1. Duration parsing
        $durationRaw = (string) ($listing['duration'] ?? '3 Days / 2 Nights');
        $days = 1;
        $nights = 0;
        if (preg_match('/(\d+)\s*(?:d|day)/i', $durationRaw, $m)) {
            $days = max(1, (int) $m[1]);
        } elseif (preg_match('/half\s*day/i', $durationRaw)) {
            $days = 1;
            $nights = 0;
        } else {
            $days = 3;
        }

        if (preg_match('/(\d+)\s*(?:n|night)/i', $durationRaw, $m)) {
            $nights = max(0, (int) $m[1]);
        } elseif ($days > 1 && !str_contains(strtolower($durationRaw), 'day tour')) {
            $nights = $days - 1;
        }

        $durationCode = $nights > 0 ? "{$days}D{$nights}N" : "{$days}D";
        $durationText = "{$days} Day" . ($days > 1 ? 's' : '') . ($nights > 0 ? " / {$nights} Night" . ($nights > 1 ? 's' : '') : ' (Day Tour)');

        // 2. Capacity & Group Size
        $maxPax = (int) ($listing['capacity'] ?? 8);
        if ($maxPax <= 0) $maxPax = 8;
        $groupSizeText = "Up to {$maxPax}";

        // 3. Overview metadata
        $categoryLabel = 'Adventure & Nature';
        $badge = GuideController::determinePackageBadge($listing, ($listing['status'] ?? '') === 'pending');
        $assurance = 'Guaranteed';

        if (preg_match('/<!--\s*PACKAGE_OVERVIEW:\s*(.*?)\s*-->/s', $cleanDesc, $om)) {
            $ov = json_decode($om[1], true) ?? [];
            if (!empty($ov['category_label'])) {
                $categoryLabel = (string) $ov['category_label'];
            }
            if (!empty($ov['badge'])) {
                $badge = (string) $ov['badge'];
            }
            if (!empty($ov['assurance'])) {
                $assurance = (string) $ov['assurance'];
            }
            $cleanDesc = trim(str_replace($om[0], '', $cleanDesc));
        }

        // 4. Extract Policies & Requirements
        $policiesText = '';
        if (preg_match('/--- Policies & Requirements ---\s*(.*)$/s', $cleanDesc, $pm)) {
            $policiesText = trim($pm[1]);
            $cleanDesc = trim(str_replace($pm[0], '', $cleanDesc));
        }

        // 5. Extract Day-by-Day Itinerary
        $itineraryRaw = '';
        if (preg_match('/--- Day-by-Day Itinerary ---\s*(.*?)(?:--- Policies & Requirements ---|$)/s', $cleanDesc, $im)) {
            $itineraryRaw = trim($im[1]);
            $cleanDesc = trim(str_replace($im[0], '', $cleanDesc));
        }

        // 6. Extract Experience Tags
        $tags = [];
        if (preg_match('/Experience Tags:\s*(.*)$/mi', $cleanDesc, $tm)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', str_replace("\n", ',', $tm[1])))));
            $cleanDesc = trim(str_replace($tm[0], '', $cleanDesc));
        }

        // 7. Parse structured itinerary days
        $itineraryDays = [];
        if ($itineraryRaw !== '') {
            $decoded = json_decode($itineraryRaw, true);
            if (is_array($decoded) && !empty($decoded)) {
                foreach ($decoded as $d) {
                    $itineraryDays[] = [
                        'day' => (int) ($d['day'] ?? (count($itineraryDays) + 1)),
                        'title' => (string) ($d['title'] ?? ('Day ' . (count($itineraryDays) + 1))),
                        'description' => (string) ($d['desc'] ?? ($d['description'] ?? '')),
                        'schedule' => is_array($d['schedule'] ?? null) ? $d['schedule'] : [],
                        'highlights' => is_array($d['highlights'] ?? null) ? $d['highlights'] : [],
                    ];
                }
            }
        }

        // If itinerary wasn't stored in JSON or was empty, supply structured fallback days based on duration
        if (empty($itineraryDays)) {
            for ($d = 1; $d <= $days; $d++) {
                if ($d === 1) {
                    $itineraryDays[] = [
                        'day' => 1,
                        'title' => 'Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary',
                        'description' => 'Meet your guide host, pick up your scooter, check in to your resort, and snorkel with turtles.',
                        'schedule' => [
                            ['time' => '01:00 PM', 'activity' => 'Resort check-in & scooter handover briefing'],
                            ['time' => '03:00 PM', 'activity' => 'Guided snorkeling session at Panagsama marine sanctuary'],
                            ['time' => '05:30 PM', 'activity' => 'Golden hour sunset viewing and leisure dinner'],
                        ],
                        'highlights' => [
                            'Pick up your Yamaha NMAX scooter in Moalboal (or complimentary town greeting)',
                            'Check-in at Sea Breeze Oceanfront Resort and freshen up',
                            'Afternoon guided snorkeling: Swim alongside millions of sardines and resident sea turtles',
                            'Sunset seafood dinner recommendation along Panagsama beachfront strip',
                        ],
                    ];
                } elseif ($d === 2) {
                    $itineraryDays[] = [
                        'day' => 2,
                        'title' => 'Day 2: Full Kawasan Canyoneering Expedition',
                        'description' => 'Guided river canyon trek, cliff jumps, natural slides, and waterfall boodle fight lunch.',
                        'schedule' => [
                            ['time' => '07:30 AM', 'activity' => 'Complimentary resort breakfast'],
                            ['time' => '08:30 AM', 'activity' => 'Scenic convoy ride to Badian Canyoneering outpost'],
                            ['time' => '09:15 AM', 'activity' => 'Canyon descent begins with certified master guide'],
                            ['time' => '01:30 PM', 'activity' => 'Finish at Main Kawasan cascade & hot boodle lunch'],
                            ['time' => '03:30 PM', 'activity' => 'Return to resort for swimming and evening rest'],
                        ],
                        'highlights' => [
                            'Full canyoneering route with certified canyon master',
                            'Cliff jumps (3m to 12m) with certified helmets & vests',
                            'Hot Filipino waterfall boodle fight lunch included',
                        ],
                    ];
                } else {
                    $itineraryDays[] = [
                        'day' => $d,
                        'title' => "Day {$d}: Coastal Scenic Tour & Departure",
                        'description' => 'Morning coastal ride, souvenir shopping, handover inspection, and travel farewell.',
                        'schedule' => [
                            ['time' => '08:00 AM', 'activity' => 'Morning leisure and checkout'],
                            ['time' => '11:00 AM', 'activity' => 'Trip conclusion, scooter return, and transfers'],
                        ],
                        'highlights' => [
                            'Morning coastal ride along South Cebu highway',
                            'Souvenir shopping assistance in Moalboal town center',
                            'Scooter handover inspection and travel farewell',
                        ],
                    ];
                }
            }
        }

        // 8. Parse Services metadata
        $serviceIds = null;
        if (preg_match('/<!--\s*PACKAGE_SERVICES:\s*(\{.*?\})\s*-->/s', $includedRaw . "\n" . $rawDesc, $jm)) {
            $serviceIds = json_decode($jm[1], true);
        }

        $thingsToDo = [];
        $attractions = [];
        $hotel = null;
        $rental = null;
        $hotelExcluded = false;
        $rentalExcluded = false;

        $activityIds = is_array($serviceIds) && !empty($serviceIds['activity_ids']) ? array_map('intval', $serviceIds['activity_ids']) : [];
        if (!empty($activityIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($activityIds), '?'));
            try {
                $rows = Database::all("
                    SELECT l.id, l.title, l.area, l.cover_image, l.price, l.price_unit, l.duration, l.summary,
                           c.slug as category_slug, c.name as category_name
                    FROM listings l
                    JOIN categories c ON c.id = l.category_id
                    WHERE l.id IN ($inPlaceholders)
                ", $activityIds);
                foreach ($rows as $r) {
                    if (($r['category_slug'] ?? '') === 'attractions' || str_contains(strtolower($r['category_slug'] ?? ''), 'attraction')) {
                        $attractions[] = $r;
                    } else {
                        $thingsToDo[] = $r;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Fallback for activities parsed by title if IDs not found
        if (empty($thingsToDo) && empty($attractions)) {
            if (preg_match('/Activities:\s*(.*)$/mi', $includedRaw, $am)) {
                $titles = array_filter(array_map('trim', explode(',', $am[1])));
                foreach ($titles as $t) {
                    try {
                        $row = Database::first("
                            SELECT l.id, l.title, l.area, l.cover_image, l.price, l.price_unit, l.duration, l.summary,
                                   c.slug as category_slug, c.name as category_name
                            FROM listings l
                            JOIN categories c ON c.id = l.category_id
                            WHERE l.title LIKE ?
                            LIMIT 1
                        ", ['%' . $t . '%']);
                        if ($row) {
                            if (($row['category_slug'] ?? '') === 'attractions' || str_contains(strtolower($row['category_slug'] ?? ''), 'attraction')) {
                                $attractions[] = $row;
                            } else {
                                $thingsToDo[] = $row;
                            }
                        } else {
                            $isAttr = stripos($t, 'falls') !== false || stripos($t, 'park') !== false || stripos($t, 'island') !== false;
                            $fallbackItem = [
                                'id' => 0,
                                'title' => $t,
                                'area' => $listing['area'] ?? 'Cebu, Philippines',
                                'cover_image' => $isAttr ? 'uploads/places/kawasan.jpg' : 'uploads/places/sardine.jpg',
                                'price' => 1500.00,
                                'price_unit' => 'person',
                                'duration' => 'Half day',
                                'category_name' => $isAttr ? 'Attractions' : 'Things to Do',
                                'category_slug' => $isAttr ? 'attractions' : 'things-to-do',
                                'summary' => $isAttr ? 'Badian Ecological Park entrance & conservation ticket' : 'Guided river trek & natural water slides',
                            ];
                            if ($isAttr) $attractions[] = $fallbackItem;
                            else $thingsToDo[] = $fallbackItem;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Hotel Accommodation
        $roomId = is_array($serviceIds) && isset($serviceIds['hotel_room_id']) ? (int) $serviceIds['hotel_room_id'] : -1;
        if ($roomId > 0) {
            try {
                $hotel = Database::first("
                    SELECT hr.id as room_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.image as room_image, hr.amenities as room_amenities,
                           l.id as hotel_listing_id, l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                           l.check_in_time, l.check_out_time, l.included as hotel_included, u.name as partner_name
                    FROM hotel_rooms hr
                    JOIN listings l ON l.id = hr.listing_id
                    JOIN users u ON u.id = l.user_id
                    WHERE hr.id = ?
                ", [$roomId]);
            } catch (\Throwable $e) {}
        } elseif ($roomId === 0) {
            $hotelExcluded = true;
        } else {
            if (preg_match('/Accommodation:\s*([^\n\r]+)/i', $includedRaw, $hm)) {
                $hText = trim($hm[1]);
                if (stripos($hText, 'none') !== false || stripos($hText, 'no hotel') !== false) {
                    $hotelExcluded = true;
                } else {
                    try {
                        $hotel = Database::first("
                            SELECT hr.id as room_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.image as room_image, hr.amenities as room_amenities,
                                   l.id as hotel_listing_id, l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                                   l.check_in_time, l.check_out_time, l.included as hotel_included, u.name as partner_name
                            FROM hotel_rooms hr
                            JOIN listings l ON l.id = hr.listing_id
                            JOIN users u ON u.id = l.user_id
                            WHERE (l.title LIKE ? OR hr.name LIKE ?)
                            ORDER BY hr.id ASC LIMIT 1
                        ", ['%' . $hText . '%', '%' . $hText . '%']);
                    } catch (\Throwable $e) {}
                }
            } else {
                $hotelExcluded = true;
            }
        }

        // Vehicle Rental
        $vehicleId = is_array($serviceIds) && isset($serviceIds['vehicle_id']) ? (int) $serviceIds['vehicle_id'] : -1;
        if ($vehicleId > 0) {
            try {
                $rental = Database::first("
                    SELECT v.id, v.make, v.model, v.type as vehicle_type, v.name as vehicle_name,
                           v.price_per_day as daily_rate, v.image as vehicle_image, v.location, v.specs,
                           u.name as partner_name
                    FROM vehicles v
                    JOIN users u ON u.id = v.user_id
                    WHERE v.id = ?
                ", [$vehicleId]);
            } catch (\Throwable $e) {}
        } elseif ($vehicleId === 0) {
            $rentalExcluded = true;
        } else {
            if (preg_match('/Transportation:\s*([^\n\r]+)/i', $includedRaw, $vm)) {
                $vText = trim($vm[1]);
                if (stripos($vText, 'none') !== false || stripos($vText, 'no vehicle') !== false) {
                    $rentalExcluded = true;
                } else {
                    try {
                        $rental = Database::first("
                            SELECT v.id, v.make, v.model, v.type as vehicle_type, v.name as vehicle_name,
                                   v.price_per_day as daily_rate, v.image as vehicle_image, v.location, v.specs,
                                   u.name as partner_name
                            FROM vehicles v
                            JOIN users u ON u.id = v.user_id
                            WHERE (v.name LIKE ? OR v.model LIKE ? OR v.make LIKE ?)
                            ORDER BY v.id ASC LIMIT 1
                        ", ['%' . $vText . '%', '%' . $vText . '%', '%' . $vText . '%']);
                    } catch (\Throwable $e) {}
                }
            } else {
                $rentalExcluded = true;
            }
        }

        // Hotel amenities decoding
        $hotelAmenities = [];
        if ($hotel) {
            if (!empty($hotel['room_amenities'])) {
                $dec = json_decode((string)$hotel['room_amenities'], true);
                if (is_array($dec)) $hotelAmenities = $dec;
            }
            if (empty($hotelAmenities) && !empty($hotel['hotel_included'])) {
                $hotelAmenities = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$hotel['hotel_included']))));
            }
            if (empty($hotelAmenities)) {
                $hotelAmenities = ['Free High-Speed Wi-Fi', 'Swimming Pool Access', 'Complimentary Daily Breakfast', 'Air Conditioning & Hot Water Shower', 'Beachfront Lounge with Sunset View'];
            }
        }

        // Vehicle specs decoding
        $vehicleSpecs = [];
        if ($rental) {
            if (!empty($rental['specs'])) {
                $dec = json_decode((string)$rental['specs'], true);
                if (is_array($dec)) $vehicleSpecs = $dec;
            }
            if (empty($vehicleSpecs)) {
                $vehicleSpecs = ['2 DOT-Certified Safety Helmets', 'Handlebar Phone Mount & USB Charger Port', 'Full Tank on Pickup', 'Comprehensive 24/7 Roadside Assistance'];
            }
        }

        // Custom perks (lines in included text not matching services/metadata)
        $customPerks = [];
        if ($includedRaw !== '') {
            $lines = array_filter(array_map('trim', explode("\n", $includedRaw)));
            foreach ($lines as $line) {
                if (str_starts_with($line, '<!--') || str_starts_with($line, 'Activities:') || str_starts_with($line, 'Accommodation:') || str_starts_with($line, 'Transportation:')) {
                    continue;
                }
                $customPerks[] = $line;
            }
        }
        if (empty($customPerks)) {
            $customPerks = [
                'All Environmental & Local Tourism Fees (Includes river rescue gear, wet shoes, and recovery hot lunch)',
                'Licensed Local Tour Guide Host & Trip Coordination',
            ];
        }

        // What's Not Included / Exclusions
        $exclusions = [];
        if ($notIncludedRaw !== '') {
            $exclusions = array_values(array_filter(array_map('trim', explode("\n", $notIncludedRaw))));
        }
        if (empty($exclusions)) {
            $exclusions = [
                'Airfare to and from Cebu (Mactan)',
                'Personal souvenir shopping & alcoholic drinks',
                'Optional zip-line upgrade at Kawasan starting point (₱600)',
            ];
        }

        // Guide host rating stats from system database
        $guideId = (int) ($guide['id'] ?? $listing['user_id'] ?? 0);
        $guideStats = ['avg_rating' => 0.0, 'review_count' => 0, 'completed' => 0];
        if ($guideId > 0) {
            try {
                $guideStats = \App\Models\Booking::guideRatingStats($guideId);
            } catch (\Throwable $e) {}
        }
        $hostRating = (float) ($guideStats['avg_rating'] ?? 0);
        $hostReviews = (int) ($guideStats['review_count'] ?? 0);
        $hostTours = (int) ($guideStats['completed'] ?? 0);
        $hostBio = !empty($guide['bio'])
            ? $guide['bio']
            : 'Native Moalboal local with 7+ years of professional outdoor guiding experience. Specializes in canyoneering safety, marine photography, and authentic Cebuano travel experiences.';

        // Package overall rating & reviews count driven strictly by real system data
        $pkgRating = 0.0;
        $pkgReviews = 0;
        if (isset($listing['avg_rating']) && (float) $listing['avg_rating'] > 0) {
            $pkgRating = round((float) $listing['avg_rating'], 1);
            $pkgReviews = (int) ($listing['review_count'] ?? 0);
        } elseif ($id > 0) {
            try {
                $revStats = Database::first("
                    SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(id) AS review_count
                    FROM reviews
                    WHERE listing_id = ?
                ", [$id]);
                if ($revStats && (int) ($revStats['review_count'] ?? 0) > 0) {
                    $pkgRating = round((float) ($revStats['avg_rating'] ?? 0), 1);
                    $pkgReviews = (int) ($revStats['review_count'] ?? 0);
                }
            } catch (\Throwable $e) {}
        }

        // Cancellation policy & requirements
        $cancellationPolicy = 'Free cancellation up to 48 hours before Day 1 start time. 50% refund within 24–48 hours. Non-refundable within 24 hours of scheduled departure.';
        $requirementsList = [
            "Valid Philippine Driver's License or International Driving Permit for scooter drivers",
            'Minimum age of 18 for scooter driver (passengers may be 12+)',
            'Moderate physical fitness required for Kawasan canyoneering (cliff jumps are optional)',
            'Bring swimwear, aqua shoes, waterproof phone pouch, and sun protection',
        ];
        if ($policiesText !== '') {
            $pLines = array_values(array_filter(array_map('trim', explode("\n", $policiesText))));
            $customReqs = [];
            $customCancel = '';
            foreach ($pLines as $pLine) {
                if (stripos($pLine, 'cancel') !== false || stripos($pLine, 'refund') !== false) {
                    $customCancel .= ($customCancel ? ' ' : '') . ltrim($pLine, "-*• \t");
                } else {
                    $customReqs[] = ltrim($pLine, "-*• \t");
                }
            }
            if ($customCancel !== '') {
                $cancellationPolicy = $customCancel;
            }
            if (!empty($customReqs)) {
                $requirementsList = $customReqs;
            }
        }

        return [
            'id' => $id,
            'title' => $title,
            'category_label' => $categoryLabel,
            'badge' => $badge,
            'assurance' => $assurance,
            'destination' => (string) ($listing['area'] ?? 'Moalboal & Badian, South Cebu'),
            'meeting_point' => (string) (!empty($listing['address']) ? $listing['address'] : (!empty($listing['meeting_point']) ? $listing['meeting_point'] : 'Moalboal, Cebu')),
            'rating' => $pkgRating,
            'review_count' => $pkgReviews,
            'duration_code' => $durationCode,
            'duration_days' => $days,
            'duration_nights' => $nights,
            'duration_text' => $durationText,
            'group_size' => $groupSizeText,
            'price' => (float) ($listing['price'] ?? 0),
            'description' => $cleanDesc !== '' ? $cleanDesc : $title,
            'tags' => $tags,
            'host' => [
                'id' => (int) ($guide['id'] ?? $listing['user_id'] ?? 1),
                'name' => (string) ($guide['name'] ?? 'Marc Repuesto'),
                'avatar' => !empty($guide['avatar']) ? $guide['avatar'] : '',
                'role' => 'Licensed Regional Tour Guide',
                'badge' => 'DOT Accredited',
                'rating' => $hostRating,
                'reviews_count' => $hostReviews,
                'tours_count' => $hostTours,
                'bio' => $hostBio,
            ],
            'inclusions' => [
                'things_to_do' => $thingsToDo,
                'attractions' => $attractions,
                'hotel' => $hotel,
                'rental' => $rental,
            ],
            'hotel_included' => !$hotelExcluded && $hotel !== null,
            'hotel' => $hotel,
            'hotel_amenities' => $hotelAmenities,
            'rental_included' => !$rentalExcluded && $rental !== null,
            'rental' => $rental,
            'rental_specs' => $vehicleSpecs,
            'custom_perks' => $customPerks,
            'exclusions' => $exclusions,
            'itinerary' => $itineraryDays,
            'policies' => $policiesText,
            'cancellation_policy' => $cancellationPolicy,
            'requirements' => $requirementsList,
        ];
    }

    public function deleteGuideListing(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Listing not found.'], 404);
                return;
            }
            flash('error', 'Listing not found.');
            redirect('/admin/guide-listings');
            return;
        }

        $status = (string) ($listing['status'] ?? '');
        if ($status !== 'rejected') {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Only rejected listings can be permanently deleted.'], 400);
                return;
            }
            flash('error', 'Only rejected listings can be permanently deleted.');
            redirect('/admin/guide-listings');
            return;
        }

        $listingId = (int) $listing['id'];
        $listingTitle = (string) $listing['title'];
        $guideId = (int) ($listing['user_id'] ?? 0);

        Listing::delete($listingId);

        AuditLog::recordAction('guide_listing.delete', 'listing', $listingId, [
            'title' => $listingTitle,
            'guide_id' => $guideId,
            'deleted_by' => AdminAuth::id(),
        ]);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => "Rejected listing '{$listingTitle}' has been permanently deleted.",
                'id' => $listingId,
            ]);
            return;
        }

        flash('success', "Rejected listing '{$listingTitle}' has been permanently deleted.");
        redirect('/admin/guide-listings');
    }

    public function updateListingStatus(string $id): void
    {
        $this->verifyCsrf();
        $status = (string) $this->input('status', '');
        if (in_array($status, ['approved', 'rejected', 'pending'], true)) {
            Listing::setStatus((int) $id, $status);
            AuditLog::recordAction('listing.status', 'listing', (int) $id, ['status' => $status]);
            flash('success', 'Listing ' . $status . '.');
        }
        redirect('/admin/listings');
    }

    public function toggleFeatured(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing !== null) {
            Listing::setFeatured((int) $id, (int) $listing['is_featured'] === 0);
            flash('success', 'Featured status updated.');
        }
        redirect('/admin/listings');
    }

    public function hotels(): void
    {
        $status = (string) $this->input('status', 'all');
        $this->view('admin/hotels', [
            'title' => 'Hotel Listings Review',
            'hotels' => Listing::allHotelsForAdmin('all'),
            'status' => $status,
            'pendingCount' => Listing::pendingHotelsCount(),
        ], 'admin');
    }

    public function showHotel(string $id): void
    {
        $hotel = Listing::find((int) $id);
        if ($hotel === null) {
            abort(404, 'Hotel listing not found.');
        }

        $provider = User::find((int) $hotel['user_id']);
        $reviewer = !empty($hotel['reviewed_by']) ? User::find((int) $hotel['reviewed_by']) : null;
        $rooms = \App\Models\HotelRoom::forListing((int) $hotel['id']);

        $this->view('admin/hotel-show', [
            'title' => 'Review Hotel: ' . $hotel['title'],
            'hotel' => $hotel,
            'provider' => $provider,
            'reviewer' => $reviewer,
            'rooms' => $rooms,
            'gallery' => Listing::gallery((int) $hotel['id']),
        ], 'admin');
    }

    public function approveHotel(string $id): void
    {
        $this->verifyCsrf();
        $hotel = Listing::find((int) $id);
        if ($hotel === null) {
            flash('error', 'Hotel not found.');
            redirect('/admin/hotels');
        }

        $adminId = (int) AdminAuth::id();
        Listing::approveHotel((int) $id, $adminId);

        AuditLog::recordAction('hotel.approve', 'listing', (int) $id, [
            'hotel_title' => $hotel['title'],
            'provider_id' => $hotel['user_id'],
        ]);

        $provider = User::find((int) $hotel['user_id']);
        if ($provider !== null && !empty($provider['email'])) {
            NotificationService::hotelApproved((string) $provider['email'], (string) $hotel['title']);
        }

        flash('success', "Hotel '{$hotel['title']}' has been approved and published.");
        redirect('/admin/hotels');
    }

    public function rejectHotel(string $id): void
    {
        $this->verifyCsrf();
        $hotel = Listing::find((int) $id);
        if ($hotel === null) {
            flash('error', 'Hotel not found.');
            redirect('/admin/hotels');
        }

        $reason = trim((string) $this->input('reason', ''));
        $adminId = (int) AdminAuth::id();
        Listing::rejectHotel((int) $id, $adminId, $reason !== '' ? $reason : null);

        AuditLog::recordAction('hotel.reject', 'listing', (int) $id, [
            'hotel_title' => $hotel['title'],
            'provider_id' => $hotel['user_id'],
            'reason' => $reason,
        ]);

        $provider = User::find((int) $hotel['user_id']);
        if ($provider !== null && !empty($provider['email'])) {
            NotificationService::hotelRejected((string) $provider['email'], (string) $hotel['title'], $reason);
        }

        flash('info', "Hotel '{$hotel['title']}' has been rejected.");
        redirect('/admin/hotels');
    }

    public function deleteHotel(string $id): void
    {
        $this->verifyCsrf();
        $hotel = Listing::find((int) $id);
        if ($hotel === null) {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Hotel listing not found.'], 404);
                return;
            }
            flash('error', 'Hotel listing not found.');
            redirect('/admin/hotels');
            return;
        }

        // Deletion is strictly permitted only for rejected listings
        $status = (string) ($hotel['status'] ?? '');
        if ($status !== 'rejected') {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Only rejected hotel listings can be permanently deleted.'], 400);
                return;
            }
            flash('error', 'Only rejected hotel listings can be permanently deleted.');
            redirect('/admin/hotels');
            return;
        }

        $hotelId = (int) $hotel['id'];
        $hotelTitle = (string) $hotel['title'];
        $providerId = (int) ($hotel['user_id'] ?? 0);

        Listing::delete($hotelId);

        AuditLog::recordAction('hotel.delete', 'listing', $hotelId, [
            'hotel_title' => $hotelTitle,
            'provider_id' => $providerId,
            'deleted_by' => AdminAuth::id(),
        ]);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => "Rejected hotel '{$hotelTitle}' has been permanently deleted.",
                'id' => $hotelId,
            ]);
            return;
        }

        flash('success', "Rejected hotel '{$hotelTitle}' has been permanently deleted.");
        redirect('/admin/hotels');
    }

    public function vehicles(): void
    {
        $status = (string) $this->input('status', 'all');
        $type = (string) $this->input('type', 'all');
        $this->view('admin/vehicles', [
            'title' => 'Vehicle Listings Review',
            'vehicles' => Vehicle::allForAdmin('all'),
            'status' => $status,
            'type' => $type,
            'pendingCount' => Vehicle::pendingCount(),
        ], 'admin');
    }

    public function showVehicle(string $id): void
    {
        $vehicle = Vehicle::find((int) $id);
        if ($vehicle === null) {
            abort(404, 'Vehicle listing not found.');
        }

        $this->view('admin/vehicle-show', [
            'title' => 'Review Vehicle: ' . $vehicle['name'],
            'vehicle' => $vehicle,
            'gallery' => Vehicle::gallery((int) $vehicle['id']),
            'specs' => Vehicle::parseSpecs($vehicle['specs'] ?? null),
        ], 'admin');
    }

    public function approveVehicle(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = Vehicle::find((int) $id);
        if ($vehicle === null) {
            flash('error', 'Vehicle not found.');
            redirect('/admin/vehicles');
        }

        $adminId = (int) AdminAuth::id();
        Vehicle::approve((int) $id, $adminId);

        AuditLog::recordAction('vehicle.approve', 'vehicle', (int) $id, [
            'vehicle_name' => $vehicle['name'],
            'provider_id' => $vehicle['user_id'],
        ]);

        if (!empty($vehicle['provider_email'])) {
            NotificationService::vehicleApproved((string) $vehicle['provider_email'], (string) $vehicle['name']);
        }

        flash('success', "Vehicle '{$vehicle['name']}' has been approved and published.");
        redirect('/admin/vehicles');
    }

    public function rejectVehicle(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = Vehicle::find((int) $id);
        if ($vehicle === null) {
            flash('error', 'Vehicle not found.');
            redirect('/admin/vehicles');
        }

        $reason = trim((string) $this->input('reason', ''));
        $adminId = (int) AdminAuth::id();
        Vehicle::reject((int) $id, $adminId, $reason !== '' ? $reason : null);

        AuditLog::recordAction('vehicle.reject', 'vehicle', (int) $id, [
            'vehicle_name' => $vehicle['name'],
            'provider_id' => $vehicle['user_id'],
            'reason' => $reason,
        ]);

        if (!empty($vehicle['provider_email'])) {
            NotificationService::vehicleRejected((string) $vehicle['provider_email'], (string) $vehicle['name'], $reason);
        }

        flash('info', "Vehicle '{$vehicle['name']}' has been rejected.");
        redirect('/admin/vehicles');
    }

    public function deleteVehicle(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = Vehicle::find((int) $id);
        if ($vehicle === null) {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Vehicle listing not found.'], 404);
                return;
            }
            flash('error', 'Vehicle listing not found.');
            redirect('/admin/vehicles');
            return;
        }

        // Deletion is strictly permitted only for rejected listings
        $status = (string) ($vehicle['status'] ?? '');
        if ($status !== 'rejected') {
            if ($this->isJson()) {
                $this->json(['success' => false, 'message' => 'Only rejected vehicle listings can be permanently deleted.'], 400);
                return;
            }
            flash('error', 'Only rejected vehicle listings can be permanently deleted.');
            redirect('/admin/vehicles');
            return;
        }

        $vehicleId = (int) $vehicle['id'];
        $vehicleName = (string) $vehicle['name'];
        $providerId = (int) ($vehicle['user_id'] ?? 0);

        Vehicle::delete($vehicleId);

        AuditLog::recordAction('vehicle.delete', 'vehicle', $vehicleId, [
            'vehicle_name' => $vehicleName,
            'provider_id' => $providerId,
            'deleted_by' => AdminAuth::id(),
        ]);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => "Rejected vehicle '{$vehicleName}' has been permanently deleted.",
                'id' => $vehicleId,
            ]);
            return;
        }

        flash('success', "Rejected vehicle '{$vehicleName}' has been permanently deleted.");
        redirect('/admin/vehicles');
    }

    public function users(): void
    {
        $appeals = AccountAppeal::all();
        $pendingAppeals = [];
        foreach ($appeals as $app) {
            if ($app['status'] === AccountAppeal::STATUS_PENDING && !empty($app['user_id'])) {
                $pendingAppeals[(int) $app['user_id']] = $app;
            }
        }

        $this->view('admin/users', [
            'title' => 'Manage Users',
            'users' => User::all(),
            'pendingAppeals' => $pendingAppeals,
        ], 'admin');
    }

    public function createUser(): void
    {
        $this->view('admin/user-form', [
            'title' => 'Add User',
            'user' => null,
            'documents' => [],
            'errors' => errors(),
        ], 'admin');
    }

    public function storeUser(): void
    {
        $this->verifyCsrf();
        $name = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $role = (string) $this->input('role', 'tourist');
        $isActive = (int) $this->input('is_active', '1');
        $phone = trim((string) $this->input('phone', ''));
        $location = trim((string) $this->input('location', ''));
        $bio = trim((string) $this->input('bio', ''));

        if ($role === 'rental_admin') {
            $role = 'rental_provider';
        }
        if ($role === 'hotel_admin') {
            $role = 'hotel_provider';
        }
        $validRoles = ['tourist', 'guide', 'hotel_provider', 'hotel_admin', 'rental_provider', 'admin'];
        if (!in_array($role, $validRoles, true)) {
            $role = 'tourist';
        }

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Full Name is required.';
        }
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (User::emailExists($email)) {
            $errors['email'] = 'This email address is already in use.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($errors !== []) {
            flash_keep_old($errors, [], '/admin/users/create');
        }

        $userId = User::adminCreate([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => $isActive,
            'phone' => $phone,
            'location' => $location,
            'bio' => $bio,
            'guide_status' => User::isProviderRole($role) ? 'approved' : 'none',
        ]);

        AuditLog::recordAction('user.create', 'user', $userId, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
        ]);

        flash('success', "User '{$name}' created successfully.");
        redirect('/admin/users');
    }

    public function editUser(string $id): void
    {
        $user = User::find((int) $id);
        if ($user === null) {
            abort(404, 'User not found.');
        }

        $documents = User::isProviderRole((string) ($user['role'] ?? ''))
            ? GuideDocument::forUser((int) $user['id'])
            : [];

        $latestAppeal = AccountAppeal::latestForUser((int) $user['id']);

        $this->view('admin/user-form', [
            'title' => 'Edit User — ' . $user['name'],
            'user' => $user,
            'documents' => $documents,
            'latestAppeal' => $latestAppeal,
            'errors' => errors(),
        ], 'admin');
    }

    public function updateUser(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if ($user === null) {
            abort(404, 'User not found.');
        }

        $name = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));
        $role = (string) $this->input('role', $user['role']);
        $isActive = (int) $this->input('is_active', (string) $user['is_active']);
        $phone = trim((string) $this->input('phone', ''));
        $location = trim((string) $this->input('location', ''));
        $bio = trim((string) $this->input('bio', ''));
        $guideStatus = (string) $this->input('guide_status', (string) ($user['guide_status'] ?? 'none'));

        if ($role === 'rental_admin') {
            $role = 'rental_provider';
        }
        if ($role === 'hotel_admin') {
            $role = 'hotel_provider';
        }
        $validRoles = ['tourist', 'guide', 'hotel_provider', 'hotel_admin', 'rental_provider', 'admin'];
        if (!in_array($role, $validRoles, true)) {
            $role = (string) $user['role'];
        }

        if ((int) $id === (int) AdminAuth::id()) {
            $role = 'admin';
            $isActive = 1;
        }

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Full Name is required.';
        }
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (User::emailExists($email, (int) $id)) {
            $errors['email'] = 'This email address is already in use.';
        }

        if ($errors !== []) {
            flash_keep_old($errors, [], '/admin/users/' . $id . '/edit');
        }

        $updateData = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
            'phone' => $phone,
            'location' => $location,
            'bio' => $bio,
        ];
        if ($isActive === 1) {
            $updateData['suspension_reason'] = null;
            $updateData['suspended_at'] = null;
            $pendingAppeal = AccountAppeal::pendingForUser((int) $id);
            if ($pendingAppeal !== null) {
                AccountAppeal::updateStatus((int) $pendingAppeal['id'], AccountAppeal::STATUS_APPROVED, 'Approved by admin on profile update', (int) AdminAuth::id());
            }
        }
        if (User::isProviderRole($role)) {
            $validStatuses = ['approved', 'pending', 'rejected', 'none'];
            if (in_array($guideStatus, $validStatuses, true)) {
                $updateData['guide_status'] = $guideStatus;
            }
        } else {
            $updateData['guide_status'] = 'none';
        }

        User::adminUpdate((int) $id, $updateData);

        AuditLog::recordAction('user.update', 'user', (int) $id, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
            'guide_status' => $updateData['guide_status'] ?? null,
        ]);

        flash('success', "User '{$name}' updated successfully.");
        redirect('/admin/users');
    }

    public function deleteUser(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if ($user === null) {
            abort(404, 'User not found.');
        }

        if ((int) $id === (int) AdminAuth::id()) {
            flash('error', 'You cannot delete your own account.');
            redirect('/admin/users');
        }

        User::delete((int) $id);
        AuditLog::recordAction('user.delete', 'user', (int) $id, [
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);

        flash('success', "User '{$user['name']}' has been deleted.");
        redirect('/admin/users');
    }

    public function toggleUser(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if ($user !== null && $user['role'] !== 'admin') {
            $active = (int) $user['is_active'] === 0;
            User::setActive((int) $id, $active);
            AuditLog::recordAction('user.toggle_active', 'user', (int) $id, ['active' => $active]);
            flash('success', 'User status updated.');
        }
        redirect('/admin/users');
    }

    public function suspendUser(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if ($user === null) {
            flash('error', 'User not found.');
            redirect('/admin/users');
        }

        if ((int) $user['id'] === (int) AdminAuth::id() || $user['role'] === 'admin') {
            flash('error', 'Administrator accounts cannot be suspended.');
            redirect('/admin/users');
        }

        $reason = trim((string) $this->input('reason', ''));
        if ($reason === '') {
            flash('error', 'A reason for suspension is required.');
            redirect('/admin/users');
        }

        User::suspend((int) $id, $reason);
        AuditLog::recordAction('user.suspend', 'user', (int) $id, [
            'name' => $user['name'],
            'email' => $user['email'],
            'reason' => $reason,
        ]);

        flash('success', "User '{$user['name']}' has been suspended.");
        redirect('/admin/users');
    }

    public function unsuspendUser(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if ($user === null) {
            flash('error', 'User not found.');
            redirect('/admin/users');
        }

        User::unsuspend((int) $id);
        $pendingAppeal = AccountAppeal::pendingForUser((int) $id);
        if ($pendingAppeal !== null) {
            AccountAppeal::updateStatus((int) $pendingAppeal['id'], AccountAppeal::STATUS_APPROVED, 'Approved by admin on reactivation', (int) AdminAuth::id());
        }

        AuditLog::recordAction('user.unsuspend', 'user', (int) $id, [
            'name' => $user['name'],
            'email' => $user['email'],
        ]);

        flash('success', "User '{$user['name']}' has been reactivated.");
        redirect('/admin/users');
    }

    public function disputes(): void
    {
        $this->view('admin/disputes', [
            'title' => 'Disputes',
            'disputes' => Dispute::pending(),
        ], 'admin');
    }

    public function resolveDispute(string $id): void
    {
        $this->verifyCsrf();
        $dispute = Dispute::find((int) $id);
        if ($dispute === null) {
            abort(404, 'Dispute not found.');
        }

        $action = (string) $this->input('action', '');
        $note = trim((string) $this->input('admin_note', ''));
        $bookingId = (int) $dispute['booking_id'];
        $guideId = (int) $dispute['guide_id'];
        $disputeId = (int) $dispute['id'];

        if ($action === 'refund') {
            Payment::refund($bookingId);
            Booking::updateStatus($bookingId, 'refunded');
            Dispute::resolve($disputeId, 'resolved_refund', $note !== '' ? $note : 'Refund approved.');
            User::appendGuideNote($guideId, 'Dispute #' . $disputeId . ': refund issued to tourist.');
            AuditLog::recordAction('dispute.refund', 'dispute', $disputeId);
            flash('success', 'Refund processed and dispute resolved.');
        } elseif ($action === 'warning') {
            $warningNote = $note !== '' ? $note : 'Warning issued after dispute #' . $disputeId . '.';
            Dispute::resolve($disputeId, 'resolved_warning', $warningNote);
            // Keep booking as disputed so the guide cannot mark it complete again;
            // the tourist has already used their one report for this booking.
            User::warnGuide($guideId, $warningNote);
            $this->notifyGuideWarning($guideId, $warningNote);
            AuditLog::recordAction('dispute.warning', 'dispute', $disputeId);
            flash('success', 'Guide warned. They were notified by message and booking actions are restricted until you clear the warning.');
        } elseif ($action === 'reject') {
            if ($dispute['booking_status'] === 'disputed') {
                Booking::updateStatus($bookingId, 'confirmed');
            }
            Dispute::resolve($disputeId, 'rejected', $note !== '' ? $note : 'Report rejected.');
            AuditLog::recordAction('dispute.reject', 'dispute', $disputeId);
            flash('info', 'Dispute rejected.');
        } elseif ($action === 'reviewing') {
            Dispute::resolve($disputeId, 'reviewing', $note !== '' ? $note : null);
            flash('info', 'Dispute marked as under review.');
        } else {
            flash('error', 'Unknown action.');
        }

        redirect('/admin/disputes');
    }

    public function warnProvider(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        $fallback = '/admin/users';
        if ($user === null || !User::isProviderRole((string) ($user['role'] ?? ''))) {
            abort(404, 'Provider not found.');
        }
        $note = trim((string) $this->input('note', 'Administrator warning issued.'));
        if ($note === '') {
            $note = 'Administrator warning issued.';
        }
        User::warnGuide((int) $id, $note);
        $this->notifyGuideWarning((int) $id, $note);
        AuditLog::recordAction('guide.warning', 'user', (int) $id);
        flash('success', "Warning issued to {$user['name']}. Booking actions (Confirm, Complete, Cancel) are restricted until you clear the warning.");
        redirect($this->adminSafeReturn($fallback));
    }

    public function clearGuideWarning(string $id): void
    {
        $this->verifyCsrf();
        $user = User::find((int) $id);
        $fallback = '/admin/users';
        // Warnings can apply to any service provider (guide, hotel or rental
        // partner), so allow clearing for all of them — not just guides.
        if ($user === null || !User::isProviderRole((string) ($user['role'] ?? ''))) {
            abort(404, 'Provider not found.');
        }
        if (!User::isGuideWarned((int) $id)) {
            flash('info', 'This partner has no active warning.');
            redirect($this->adminSafeReturn($fallback));
        }

        User::clearGuideWarning((int) $id);
        AuditLog::recordAction('guide.clear_warning', 'user', (int) $id);
        $this->notifyGuideWarningCleared((int) $id);
        flash('success', 'Account unrestricted. This partner can manage bookings again and was notified.');
        redirect($this->adminSafeReturn($fallback));
    }

    /**
     * Allow posting forms to send the admin back to Messages (or another
     * admin page) after unrestricting, without open-redirecting off-site.
     */
    private function adminSafeReturn(string $fallback): string
    {
        $return = trim((string) $this->input('return', $fallback));
        if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//') || !str_starts_with($return, '/admin')) {
            return $fallback;
        }
        return $return;
    }

    /**
     * Admin inbox — conversations with providers, including their replies to
     * warnings. Uses the shared "Administrator" account so every admin sees the
     * same history (warnings are sent from that same account).
     */
    public function messages(): void
    {
        $adminId = $this->adminMessagingId();
        $this->view('admin/messages', [
            'title' => 'Messages',
            'conversations' => Message::conversations($adminId),
            'partner' => null,
            'thread' => [],
            'adminId' => $adminId,
        ], 'admin');
    }

    public function messageThread(string $partner): void
    {
        $adminId = $this->adminMessagingId();
        $partnerId = (int) $partner;
        $partnerUser = User::find($partnerId);
        if ($partnerUser === null) {
            abort(404, 'Conversation not found.');
        }
        Message::markRead($adminId, $partnerId);
        $this->view('admin/messages', [
            'title' => 'Messages',
            'conversations' => Message::conversations($adminId),
            'partner' => $partnerUser,
            'thread' => Message::thread($adminId, $partnerId),
            'adminId' => $adminId,
        ], 'admin');
    }

    public function poll(string $partner): void
    {
        $adminId = $this->adminMessagingId();
        $partnerId = (int) $partner;
        $partnerUser = User::find($partnerId);
        if ($partnerUser === null) {
            $this->json(['error' => 'Conversation not found.'], 404);
            return;
        }
        $since = (int) $this->input('since', 0);
        $messages = Message::since($adminId, $partnerId, $since);
        if ($messages !== []) {
            Message::markRead($adminId, $partnerId);
        }

        $formatted = array_map(function ($m) use ($adminId) {
            return [
                'id' => (int) $m['id'],
                'sender_id' => (int) $m['sender_id'],
                'receiver_id' => (int) $m['receiver_id'],
                'body' => (string) $m['body'],
                'created_at' => (string) $m['created_at'],
                'time_formatted' => date('g:i a', strtotime((string) $m['created_at'])),
                'is_mine' => (int) $m['sender_id'] === $adminId,
            ];
        }, $messages);

        $this->json(['messages' => $formatted]);
    }

    public function sendMessage(): void
    {
        $this->verifyCsrf();
        $adminId = $this->adminMessagingId();
        $receiverId = (int) $this->input('receiver_id', 0);
        $body = trim((string) $this->input('body', ''));

        if ($receiverId === 0 || $receiverId === $adminId || $body === '') {
            if ($this->isJson()) {
                $this->json(['error' => 'Invalid recipient or empty message.'], 422);
                return;
            }
            redirect('/admin/messages' . ($receiverId ? '/' . $receiverId : ''));
        }
        if (User::find($receiverId) === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'Recipient not found.'], 404);
                return;
            }
            abort(404, 'Recipient not found.');
        }

        $msgId = Message::send($adminId, $receiverId, $body);
        AuditLog::recordAction('admin.message', 'user', $receiverId);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => [
                    'id' => $msgId,
                    'sender_id' => $adminId,
                    'receiver_id' => $receiverId,
                    'body' => $body,
                    'created_at' => date('Y-m-d H:i:s'),
                    'time_formatted' => date('g:i a'),
                    'is_mine' => true,
                ],
            ]);
            return;
        }

        redirect('/admin/messages/' . $receiverId);
    }

    /**
     * The shared "Administrator" user id used for all admin messaging, so
     * replies land in one place regardless of which admin is signed in.
     */
    private function adminMessagingId(): int
    {
        $admin = User::adminAccount();
        return (int) ($admin['id'] ?? AdminAuth::id() ?? 0);
    }

    public function analytics(): void
    {
        $this->view('admin/analytics', [
            'title' => 'Analytics',
            'bookingsByMonth' => Booking::bookingsPerMonth(12),
            'topListings' => Listing::topByBookings(10),
            'usersByMonth' => User::registrationsPerMonth(12),
            'disputeStats' => Dispute::rateStats(),
            'revenue' => Payment::totalRevenue(),
            'auditLog' => AuditLog::recent(25),
        ], 'admin');
    }

    public function exportBookings(): void
    {
        $rows = Booking::exportRows();
        $this->streamCsv('bookings.csv', [
            'ID', 'Listing', 'Customer', 'Date', 'Guests', 'Total', 'Status', 'Created',
        ], $rows);
    }

    public function exportUsers(): void
    {
        $rows = User::exportRows();
        $this->streamCsv('users.csv', [
            'ID', 'Name', 'Email', 'Role', 'Guide Status', 'Active', 'Joined',
        ], $rows);
    }

    public function exportDisputes(): void
    {
        $rows = [];
        foreach (Dispute::all() as $d) {
            $rows[] = [
                $d['id'],
                $d['listing_title'],
                $d['tourist_name'],
                $d['guide_name'],
                Dispute::typeLabel((string) $d['problem_type']),
                Dispute::statusLabel((string) $d['status']),
                $d['total_amount'],
                $d['created_at'],
            ];
        }
        $this->streamCsv('disputes.csv', [
            'ID', 'Listing', 'Tourist', 'Guide', 'Problem', 'Status', 'Booking Total', 'Created',
        ], $rows);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    private function streamCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            abort(500, 'Could not export CSV.');
        }
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function notifyGuideWarning(int $guideId, string $note): void
    {
        $admin = User::adminAccount();
        if ($admin === null) {
            return;
        }
        $body = "Administrator Warning\n\n"
            . "You have received an official warning from the GuideMate Administrator.\n\n"
            . "Reason: {$note}\n\n"
            . "Your booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. "
            . "Please review this message and wait for admin follow-up.";
        Message::send((int) $admin['id'], $guideId, $body);
    }

    private function notifyGuideWarningCleared(int $guideId): void
    {
        $admin = User::adminAccount();
        if ($admin === null) {
            return;
        }
        $body = "Administrator Notice\n\n"
            . "Your account has been reviewed and unrestricted by the Administrator. "
            . "You may Confirm, Complete, and Cancel bookings again.";
        Message::send((int) $admin['id'], $guideId, $body);
    }

    private function notifyPartnerDecision(int $partnerId, bool $approved, string $note = ''): void
    {
        $admin = User::adminAccount();
        if ($admin === null) {
            return;
        }
        if ($approved) {
            $body = "Application Approved\n\n"
                . "Your partner application has been approved by the GuideMate Administrator. "
                . "You can now use your partner account on GuideMate.";
        } else {
            $body = "Application Rejected\n\n"
                . "Your partner application was not approved.";
            if ($note !== '') {
                $body .= "\n\nReason: {$note}";
            }
        }
        Message::send((int) $admin['id'], $partnerId, $body);
    }
}
