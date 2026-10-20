const fs = require('fs');

let rr = fs.readFileSync('app/Models/RentalRequest.php', 'utf8');
const hasCRLF = rr.includes('\r\n');
if (hasCRLF) rr = rr.replace(/\r\n/g, '\n');

const oldTarget = `                return [
                    'id' => (string) $v['id'],
                    'slug' => (string) $v['slug'],
                    'name' => (string) $v['name'],
                    'make' => (string) $v['make'],
                    'model' => (string) $v['model'],
                    'year' => (int) $v['year'],
                    'type' => (string) $v['type'],
                    'price_per_day' => (float) $v['price_per_day'],
                    'specs' => Vehicle::parseSpecs($v['specs'] ?? null),
                    'shop' => (string) ($v['provider_name'] ?? 'Rental Partner'),
                    'provider_id' => (int) ($v['user_id'] ?? 0),
                    'provider_name' => (string) ($v['provider_name'] ?? 'Rental Partner'),
                    'provider_email' => (string) ($v['provider_email'] ?? ''),
                    'location' => (string) ($v['location'] ?? 'Cebu'),
                    'address' => (string) ($v['address'] ?? ''),
                    'description' => (string) ($v['description'] ?? ''),
                    'status' => (string) ($v['status'] ?? 'approved'),
                    'is_available' => (bool) ($v['is_available'] ?? true),
                    'rating' => 4.8,
                    'image' => $mainImg,
                    'images' => $images,
                ];`;

const newTarget = `                $stats = RentalReview::vehicleStats((string) $v['id']);
                $vRating = $stats['rating'];
                if ($vRating <= 0.0) {
                    $pStats = RentalReview::providerStats((int) ($v['user_id'] ?? 0));
                    $vRating = $pStats['rating'];
                }

                return [
                    'id' => (string) $v['id'],
                    'slug' => (string) $v['slug'],
                    'name' => (string) $v['name'],
                    'make' => (string) $v['make'],
                    'model' => (string) $v['model'],
                    'year' => (int) $v['year'],
                    'type' => (string) $v['type'],
                    'price_per_day' => (float) $v['price_per_day'],
                    'specs' => Vehicle::parseSpecs($v['specs'] ?? null),
                    'shop' => (string) ($v['provider_name'] ?? 'Rental Partner'),
                    'provider_id' => (int) ($v['user_id'] ?? 0),
                    'provider_name' => (string) ($v['provider_name'] ?? 'Rental Partner'),
                    'provider_email' => (string) ($v['provider_email'] ?? ''),
                    'location' => (string) ($v['location'] ?? 'Cebu'),
                    'address' => (string) ($v['address'] ?? ''),
                    'description' => (string) ($v['description'] ?? ''),
                    'status' => (string) ($v['status'] ?? 'approved'),
                    'is_available' => (bool) ($v['is_available'] ?? true),
                    'rating' => (float) $vRating,
                    'image' => $mainImg,
                    'images' => $images,
                ];`;

if (!rr.includes(oldTarget)) {
  console.error('Could not find oldTarget in RentalRequest.php');
  process.exit(1);
}

rr = rr.replace(oldTarget, newTarget);
if (hasCRLF) rr = rr.replace(/\n/g, '\r\n');
fs.writeFileSync('app/Models/RentalRequest.php', rr, 'utf8');
console.log('RentalRequest.php updated with dynamic rating successfully!');
