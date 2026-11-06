import { restoreSession } from '../lib/authStore';
import { API_BASE_URL } from '../config';
import { api } from './api';

export type PackageCategory = 'all' | 'multi-day' | 'day-tour' | 'adventure' | 'island-escape';

export type PackageInclusionType = 'attraction' | 'activity' | 'accommodation' | 'rental' | 'perk';

export type PackageInclusionItem = {
  type: PackageInclusionType;
  title: string;
  subtitle?: string;
  icon: string;
  badge?: string;
  details?: string;
};

export type ItineraryDay = {
  day: number;
  title: string;
  description: string;
  highlights: string[];
  schedule?: { time: string; activity: string }[];
};

export type PackageHost = {
  id: number;
  name: string;
  avatar?: string;
  role: string;
  badge: string;
  rating: number;
  reviewCount: number;
  toursCount: number;
  bio: string;
  responseRate?: string;
  verified: boolean;
};

export type TravelPackage = {
  id: string;
  slug: string;
  title: string;
  tagline: string;
  description: string;
  destination: string;
  duration: string; // e.g. "3D2N", "2D1N", "Full Day"
  durationDays: number;
  durationNights: number;
  category: 'adventure' | 'island-escape' | 'day-tour' | 'cultural';
  categoryLabel: string;
  price: number;
  originalPrice?: number;
  badge?: string; // e.g. "Best Seller", "Featured", "Popular"
  assurance?: string; // e.g. "Guaranteed", "Instant Confirmation"
  featured?: boolean;
  coverImage: string;
  localImageKey?: 'kawasan' | 'sardine' | 'whaleshark' | 'templeofleah' | 'bantayan' | 'osmena' | 'magellan';
  gallery: string[];
  rating: number;
  reviewCount: number;
  maxGroupSize: number;
  minGroupSize?: number;
  minPax?: number;
  maxPax?: number;
  meetingPoint: string;
  tags?: string[];
  host: PackageHost;
  inclusions: PackageInclusionItem[];
  exclusions: string[];
  itinerary: ItineraryDay[];
  hotelIncluded?: boolean;
  accommodationDetail?: {
    hotelName: string;
    roomType: string;
    nights: number;
    amenities: string[];
    partnerName?: string;
    checkIn?: string;
    checkOut?: string;
    coverImage?: string;
  } | null;
  rentalIncluded?: boolean;
  rentalDetail?: {
    vehicleType: string;
    model: string;
    duration: string;
    features: string[];
    partnerName?: string;
    location?: string;
    image?: string;
    handover?: string;
    dropoff?: string;
  } | null;
  policy?: {
    cancellation: string;
    confirmation: string;
    requirements: string[];
  };
};

export type PackageBookingRequest = {
  packageId: string;
  travelDate: string;
  guestsCount: number;
  notes?: string;
  contactName: string;
  contactPhone: string;
  contactEmail?: string;
  paymentMethod: 'ewallet' | 'instapay' | 'card' | 'cash';
  paymentReference?: string;
  packageBasePrice?: number;
  taxes?: number;
  environmentalFees?: number;
  voucherCode?: string;
  voucherDiscount?: number;
  finalTotal?: number;
};

export type PackageBookingResponse = {
  success: boolean;
  bookingId: string;
  packageTitle: string;
  totalPrice: number;
  status: 'confirmed' | 'pending';
  travelDate: string;
  guestsCount: number;
  guideName: string;
  message: string;
};

export const MOCK_PACKAGES: TravelPackage[] = [
  {
    id: 'pkg-new-travel-package',
    slug: 'new-travel-package',
    title: 'New Travel Package: 3D2N Ultimate South Cebu Odyssey',
    tagline: 'Curated all-inclusive travel package featuring Kawasan canyoneering, beachfront resort stay, and scooter rental.',
    description:
      'Experience the best of South Cebu with this newly created travel package hosted by local guide Marc Repuesto. Includes full canyoneering and snorkeling activities, 2 nights stay at Sea Breeze Oceanfront Resort, and private Yamaha NMAX scooter transport.',
    destination: 'Moalboal & Badian, South Cebu',
    duration: '3D2N',
    durationDays: 3,
    durationNights: 2,
    category: 'adventure',
    categoryLabel: 'Adventure & Nature',
    price: 7500,
    originalPrice: 8900,
    badge: 'New Package',
    featured: true,
    coverImage: 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
    localImageKey: 'kawasan',
    gallery: [
      'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
      'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
      'https://images.unsplash.com/photo-1558981806-ec527fa84c39?w=1200',
    ],
    rating: 5.0,
    reviewCount: 1,
    maxGroupSize: 6,
    meetingPoint: 'Moalboal Tourism Center or Sea Breeze Resort Lobby',
    host: {
      id: 2,
      name: 'Marc Repuesto',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
      role: 'Licensed Regional Tour Guide',
      badge: 'DOT Accredited',
      rating: 5.0,
      reviewCount: 48,
      toursCount: 112,
      bio: 'Native Moalboal local with 7+ years of professional outdoor guiding experience.',
      responseRate: '100% (within 10 mins)',
      verified: true,
    },
    inclusions: [
      {
        type: 'activity',
        title: 'Full Kawasan Canyoneering & Cliff Jumps',
        subtitle: 'Guided river trek with certified master guides',
        icon: 'compass-outline',
        badge: 'Things to Do',
      },
      {
        type: 'activity',
        title: 'Moalboal Sardine & Turtle Snorkel',
        subtitle: 'Panagsama reef marine sanctuary tour',
        icon: 'fish-outline',
        badge: 'Things to Do',
      },
      {
        type: 'accommodation',
        title: 'Sea Breeze Oceanfront Resort',
        subtitle: '2 Nights • Deluxe Poolside Room with daily breakfast',
        icon: 'bed-outline',
        badge: 'Hotel & Stays',
      },
      {
        type: 'rental',
        title: 'Yamaha NMAX 155cc Automatic Scooter',
        subtitle: '3-Day rental with helmets, insurance, and phone mount',
        icon: 'car-sport-outline',
        badge: 'Vehicle Rental',
      },
      {
        type: 'perk',
        title: 'Licensed Tour Guide & Coordination',
        subtitle: 'Host assistance, permits, and conservation fees included',
        icon: 'shield-checkmark-outline',
        badge: 'Included Perk',
      },
    ],
    exclusions: [
      'Airfare to and from Cebu',
      'Personal souvenir shopping & alcoholic drinks',
    ],
    itinerary: [
      {
        day: 1,
        title: 'Day 1: Arrival & Moalboal Marine Sanctuary',
        description: 'Meet guide host Marc, check in to resort, pick up scooter, and snorkel with sea turtles.',
        highlights: ['Resort check-in', 'Scooter handover', 'Guided snorkeling session'],
        schedule: [
          { time: '01:00 PM', activity: 'Resort check-in and scooter handover' },
          { time: '03:00 PM', activity: 'Panagsama marine sanctuary snorkel' },
        ],
      },
      {
        day: 2,
        title: 'Day 2: Full Kawasan Canyoneering Expedition',
        description: 'Guided canyon trek, cliff jumps, natural slides, and waterfall boodle fight lunch.',
        highlights: ['Full canyoneering route', 'Cliff jumps (3m to 12m)', 'Hot lunch included'],
        schedule: [
          { time: '08:30 AM', activity: 'Convoy ride to Badian registration outpost' },
          { time: '09:15 AM', activity: 'Canyon descent begins' },
          { time: '01:30 PM', activity: 'Kawasan cascade boodle lunch' },
        ],
      },
      {
        day: 3,
        title: 'Day 3: Coastal Cruise & Departure',
        description: 'Morning coastal ride, souvenir shopping, scooter return, and farewell.',
        highlights: ['Morning coastal ride', 'Souvenir stops', 'Vehicle return'],
        schedule: [
          { time: '09:00 AM', activity: 'Leisure morning and breakfast' },
          { time: '12:00 PM', activity: 'Resort checkout and scooter return' },
        ],
      },
    ],
    policy: {
      cancellation: 'Free cancellation up to 48 hours before departure.',
      confirmation: 'Instant confirmation with guide contact info provided immediately.',
      requirements: ['Valid ID for all guests', 'Valid driver license for scooter'],
    },
  },
  {
    id: 'pkg-south-cebu-3d2n',
    slug: 'south-cebu-expedition-3d2n',
    title: '3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter',
    tagline: 'The ultimate all-inclusive South Cebu adventure curated by DOT-certified local guide Marc.',
    description:
      'Immerse yourself in the thrill of South Cebu. This comprehensive 3-day travel bundle combines the world-famous Kawasan Falls Canyoneering, the hypnotic Moalboal Sardine Run and sea turtle sanctuary, 2 nights at a beachfront resort in Moalboal, and a dedicated 155cc Yamaha NMAX scooter for ultimate freedom across coastal vistas. Every permit, entrance, safety gear, and licensed guide assistance is completely covered.',
    destination: 'Moalboal & Badian, South Cebu',
    duration: '3D2N',
    durationDays: 3,
    durationNights: 2,
    category: 'adventure',
    categoryLabel: 'Adventure & Nature',
    price: 8499,
    originalPrice: 9900,
    badge: 'Best Seller',
    featured: true,
    coverImage: 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
    localImageKey: 'kawasan',
    gallery: [
      'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
      'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
      'https://images.unsplash.com/photo-1558981806-ec527fa84c39?w=1200',
    ],
    rating: 4.9,
    reviewCount: 38,
    maxGroupSize: 8,
    meetingPoint: 'Sea Breeze Resort Lobby or Panagsama Beach Tourism Center, Moalboal',
    host: {
      id: 2,
      name: 'Marc Repuesto',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
      role: 'Licensed Regional Tour Guide',
      badge: 'DOT Accredited',
      rating: 4.9,
      reviewCount: 48,
      toursCount: 112,
      bio: 'Native Moalboal local with 7+ years of professional outdoor guiding experience. Specializes in canyoneering safety, marine photography, and authentic Cebuano travel experiences.',
      responseRate: '100% (within 10 mins)',
      verified: true,
    },
    inclusions: [
      {
        type: 'attraction',
        title: 'Kawasan Falls Natural Park',
        subtitle: 'Badian Ecological Park entrance & conservation ticket',
        icon: 'water-outline',
        badge: 'Attraction',
      },
      {
        type: 'activity',
        title: 'Full Kawasan Canyoneering & Cliff Jumps',
        subtitle: 'Guided river trek with 7 cliff jumps (3m to 12m) & natural water slides',
        icon: 'compass-outline',
        badge: 'Things to Do',
      },
      {
        type: 'activity',
        title: 'Moalboal Sardine Run & Sea Turtle Snorkel',
        subtitle: 'Panagsama reef marine sanctuary with mask, snorkel, and life vest',
        icon: 'fish-outline',
        badge: 'Things to Do',
      },
      {
        type: 'accommodation',
        title: 'Sea Breeze Oceanfront Resort',
        subtitle: '2 Nights • Deluxe Queen Room with balcony & pool access',
        icon: 'bed-outline',
        badge: 'Hotel & Stays',
      },
      {
        type: 'rental',
        title: 'Yamaha NMAX 155cc Automatic Scooter',
        subtitle: '3-Day rental with 2 helmets, phone mount, and insurance',
        icon: 'car-sport-outline',
        badge: 'Vehicle Rental',
      },
      {
        type: 'perk',
        title: 'All Environmental & Local Tourism Fees',
        subtitle: 'Includes river rescue gear, wet shoes, and recovery hot lunch',
        icon: 'shield-checkmark-outline',
        badge: 'Included Perk',
      },
    ],
    exclusions: [
      'Airfare to and from Cebu (Mactan)',
      'Personal souvenir shopping & alcoholic drinks',
      'Optional zip-line upgrade at Kawasan starting point (₱600)',
    ],
    itinerary: [
      {
        day: 1,
        title: 'Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary',
        description: 'Meet your guide host Marc, pick up your scooter, check in to your resort, and snorkel with turtles.',
        highlights: [
          'Pick up your Yamaha NMAX scooter in Moalboal (or complimentary town greeting)',
          'Check-in at Sea Breeze Oceanfront Resort and freshen up',
          'Afternoon guided snorkeling: Swim alongside millions of sardines and resident sea turtles',
          'Sunset seafood dinner recommendation along Panagsama beachfront strip',
        ],
        schedule: [
          { time: '01:00 PM', activity: 'Resort check-in & scooter handover briefing' },
          { time: '03:00 PM', activity: 'Guided snorkeling session at Panagsama marine sanctuary' },
          { time: '05:30 PM', activity: 'Golden hour sunset viewing and leisure dinner' },
        ],
      },
      {
        day: 2,
        title: 'Day 2: Full Kawasan Canyoneering Adrenaline Expedition',
        description: 'The highlight adventure! Trek, leap, swim, and slide through the turquoise river canyon of Badian.',
        highlights: [
          'Hearty breakfast at resort before morning departure',
          'Scenic 25-minute coastal scooter ride to Badian registration outpost',
          'Complete safety briefing, fitting of certified helmets and life vests',
          'Trek through lush tropical jungle into the canyon headwaters',
          'Leap into crystalline turquoise pools (jumps optional, safe paths available)',
          'Feast on traditional Filipino boodle lunch beside the final waterfall cascade',
        ],
        schedule: [
          { time: '07:30 AM', activity: 'Complimentary resort breakfast' },
          { time: '08:30 AM', activity: 'Scenic convoy ride to Badian Canyoneering base' },
          { time: '09:15 AM', activity: 'Canyon descent begins with certified master guide' },
          { time: '01:30 PM', activity: 'Finish at Main Kawasan cascade & hot boodle fight lunch' },
          { time: '03:30 PM', activity: 'Return to resort for swimming and evening rest' },
        ],
      },
      {
        day: 3,
        title: 'Day 3: Pescador Island Boat Tour & Farewell Cebu',
        description: 'Morning marine reserve boat ride, scenic cliff coastal cruise, and flexible check-out.',
        highlights: [
          'Morning outrigger boat cruise to Pescador Island marine park',
          'Coral reef drift snorkeling over underwater drop-offs',
          'Return to resort for shower and packing',
          'Scooter return inspection and travel assistance for your next destination',
        ],
        schedule: [
          { time: '07:00 AM', activity: 'Early morning Pescador Island boat expedition' },
          { time: '10:00 AM', activity: 'Return to resort, pack, and relax by the pool' },
          { time: '12:00 PM', activity: 'Check out and hand back scooter' },
        ],
      },
    ],
    accommodationDetail: {
      hotelName: 'Sea Breeze Oceanfront Resort',
      roomType: 'Deluxe Queen Room (Balcony with sea & pool view)',
      nights: 2,
      amenities: [
        'Free High-Speed Wi-Fi',
        'Swimming Pool Access',
        'Complimentary Daily Breakfast',
        'Air Conditioning & Hot Water Shower',
        'Beachfront Lounge with Sunset View',
      ],
      partnerName: 'Sea Breeze Hospitality Group',
    },
    rentalDetail: {
      vehicleType: 'Automatic Scooter',
      model: 'Yamaha NMAX 155cc ABS',
      duration: '3 Full Days (Unlimited South Cebu mileage)',
      features: [
        '2 DOT-Certified Safety Helmets',
        'Handlebar Phone Mount & USB Charger Port',
        'Full Tank on Pickup',
        'Comprehensive 24/7 Roadside Assistance',
      ],
      partnerName: 'Cebu Swift Rentals',
    },
    policy: {
      cancellation: 'Free cancellation up to 48 hours before departure. 50% refund within 24-48 hours.',
      confirmation: 'Instant confirmation. Guide Marc will reach out via in-app chat immediately after booking.',
      requirements: [
        'Valid Government ID or Passport for all travelers',
        'Valid Motorcycle / Driver’s license for scooter rider',
        'Moderate physical fitness for canyoneering (swimming skills not mandatory as vests are required)',
      ],
    },
  },
  {
    id: 'pkg-oslob-sumilon-2d1n',
    slug: 'oslob-whale-shark-sumilon-2d1n',
    title: '2D1N Oslob Whale Shark & Sumilon Island Sandbar Retreat',
    tagline: 'Swim alongside gentle ocean giants and lounge on private turquoise coral sandbars.',
    description:
      'Experience the magic of South-Eastern Cebu in pure comfort. Hosted by marine conservation advocate Maria Santos, this curated 2-day escape features the world-renowned Oslob Whale Shark interaction, an exclusive boat crossing to Sumilon Island’s shifting sandbar, 1 night stay at Bluewater Sumilon Resort, and private roundtrip van transportation from Cebu City or Mactan.',
    destination: 'Oslob & Sumilon Island, Cebu',
    duration: '2D1N',
    durationDays: 2,
    durationNights: 1,
    category: 'island-escape',
    categoryLabel: 'Island & Marine',
    price: 6200,
    originalPrice: 7450,
    badge: 'Featured',
    featured: true,
    coverImage: 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
    localImageKey: 'whaleshark',
    gallery: [
      'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
      'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=1200',
    ],
    rating: 4.9,
    reviewCount: 29,
    maxGroupSize: 10,
    meetingPoint: 'Cebu City / Mactan Hotel Lobby (Private Door-to-Door Pickup Included)',
    host: {
      id: 3,
      name: 'Maria Santos',
      avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300',
      role: 'Marine Biologist & Tour Guide',
      badge: 'DOT Accredited',
      rating: 5.0,
      reviewCount: 32,
      toursCount: 84,
      bio: 'Passionate marine conservationist born in Oslob. Dedicated to ethical eco-tourism, respectful wildlife encounters, and warm Cebuano hospitality.',
      responseRate: '98% (within 15 mins)',
      verified: true,
    },
    inclusions: [
      {
        type: 'attraction',
        title: 'Sumilon Island Protected Sandbar',
        subtitle: 'Round-trip private boat transfers and marine reserve access',
        icon: 'sunny-outline',
        badge: 'Attraction',
      },
      {
        type: 'activity',
        title: 'Oslob Whale Shark Eco Encounter',
        subtitle: 'Early priority boat slot, briefing, snorkel gear & underwater photography guidance',
        icon: 'water-outline',
        badge: 'Things to Do',
      },
      {
        type: 'attraction',
        title: 'Tumalog Falls Enchanted Cascade',
        subtitle: 'Visit the ethereal curtain waterfall nestled in pristine limestone cliffs',
        icon: 'leaf-outline',
        badge: 'Attraction',
      },
      {
        type: 'accommodation',
        title: 'Bluewater Sumilon Island Resort',
        subtitle: '1 Night • Deluxe Island Villa with breakfast buffet',
        icon: 'bed-outline',
        badge: 'Hotel & Stays',
      },
      {
        type: 'rental',
        title: 'Toyota HiAce Commuter Van Transfer',
        subtitle: 'Private round-trip door-to-door transfer with professional chauffeur',
        icon: 'bus-outline',
        badge: 'Vehicle Rental',
      },
      {
        type: 'perk',
        title: 'Sumilon Island Picnic Lunch Buffet',
        subtitle: 'Fresh grilled Cebuano specialties and tropical juices',
        icon: 'restaurant-outline',
        badge: 'Included Perk',
      },
    ],
    exclusions: [
      'Personal scuba equipment rental (optional upgrade)',
      'Gratuities for driver and boatmen',
    ],
    itinerary: [
      {
        day: 1,
        title: 'Day 1: Southbound Coastline Cruise & Sumilon Island Sandbar',
        description: 'Morning van pickup, scenic coastal drive, boat ride to Sumilon, and resort relaxation.',
        highlights: [
          'Early morning pickup from your Cebu City or Mactan hotel in a private Toyota HiAce van',
          'Arrive at Oslob mainland and board the scenic 15-minute boat to Sumilon Island',
          'Check-in at Bluewater Sumilon Resort',
          'Lounge on the shifting white sandbar surrounded by turquoise lagoons',
          'Lagoon kayaking and nature trail walk to the historic 19th-century Spanish watchtower',
        ],
        schedule: [
          { time: '06:00 AM', activity: 'Private door-to-door van pickup' },
          { time: '09:30 AM', activity: 'Boat crossing to Sumilon Island' },
          { time: '12:00 PM', activity: 'Sumilon beachfront buffet lunch' },
          { time: '02:00 PM', activity: 'Sandbar snorkeling and kayak adventure' },
        ],
      },
      {
        day: 2,
        title: 'Day 2: Whale Shark Encounter & Tumalog Falls',
        description: 'Early morning interaction with whale sharks followed by Tumalog Falls mist.',
        highlights: [
          'Pre-dawn priority boat back to Oslob marine sanctuary for first-light whale shark viewing',
          'Snorkel alongside the gentle giants with certified marine guide Maria',
          'Short scenic ride up the mountain to Tumalog Falls curtain cascades',
          'Return van trip to Cebu City with optional Carcar Lechon lunch stop',
        ],
        schedule: [
          { time: '06:30 AM', activity: 'Priority whale shark briefing and snorkeling slot' },
          { time: '08:30 AM', activity: 'Tumalog Falls exploration & bamboo raft photography' },
          { time: '11:00 AM', activity: 'Check out and board return van transfer' },
          { time: '03:30 PM', activity: 'Arrival back at Cebu City hotel' },
        ],
      },
    ],
    accommodationDetail: {
      hotelName: 'Bluewater Sumilon Island Resort',
      roomType: 'Deluxe Island Room (Air-conditioned, private balcony)',
      nights: 1,
      amenities: [
        'Infinity Pool overlooking the Cebu Strait',
        'Private Island Beach & Sandbar Access',
        'Complimentary Island Breakfast & Welcome Drinks',
        'Kayaks and Snorkeling Gear Included',
      ],
      partnerName: 'Bluewater Resorts Group',
    },
    rentalDetail: {
      vehicleType: 'Private Passenger Van',
      model: 'Toyota HiAce Grandia / Commuter',
      duration: 'Round-trip door-to-door service (Cebu City <-> Oslob)',
      features: [
        'Dedicated professional driver & fuel included',
        'Spacious captain seats & dual air-conditioning',
        'Complimentary cold bottled waters on board',
      ],
      partnerName: 'Cebu Island Transport Co.',
    },
    policy: {
      cancellation: 'Free cancellation up to 72 hours prior to scheduled pickup date.',
      confirmation: 'Instant confirmation with QR travel voucher issued immediately.',
      requirements: [
        'Guests must follow strict Oslob marine wildlife guidelines (no sunscreen with oxybenzone, 3m distance)',
      ],
    },
  },
  {
    id: 'pkg-cebu-highlands-1day',
    slug: 'cebu-highlands-cultural-heritage-1day',
    title: '1-Day Cebu Highlands & Cultural Heritage Showcase',
    tagline: 'Discover panoramic mountain crests, blooming flower gardens, and historic landmark treasures.',
    description:
      'The definitive cultural and mountain ridge day tour of Cebu. Curated and guided by heritage specialist Carlos Dizon, this seamless full-day experience takes you from ancient Spanish fortresses in downtown Cebu to the breathtaking Temple of Leah and colorful Sirao Flower Gardens perched 2,000 feet above the city in the Transcentral Highway mountains.',
    destination: 'Busay Mountain Ridge & Cebu City',
    duration: 'Full Day (8h)',
    durationDays: 1,
    durationNights: 0,
    category: 'day-tour',
    categoryLabel: 'Culture & Scenic',
    price: 2450,
    originalPrice: 2950,
    badge: 'Day Trip',
    featured: false,
    coverImage: 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=1200',
    localImageKey: 'templeofleah',
    gallery: [
      'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=1200',
      'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=1200',
    ],
    rating: 4.8,
    reviewCount: 44,
    maxGroupSize: 6,
    meetingPoint: 'Hotel Pickup anywhere in Cebu City or Mandaue',
    host: {
      id: 4,
      name: 'Carlos Dizon',
      avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300',
      role: 'Heritage & Architectural Guide',
      badge: 'Master Guide',
      rating: 4.8,
      reviewCount: 65,
      toursCount: 140,
      bio: 'Lifelong Cebuano historian with a deep passion for architectural heritage, colonial folklore, and local mountain coffee culture.',
      responseRate: '95% (within 30 mins)',
      verified: true,
    },
    inclusions: [
      {
        type: 'attraction',
        title: 'Temple of Leah (Cebu’s Taj Mahal)',
        subtitle: 'Neoclassical Roman temple with panoramic skyline views of Cebu City',
        icon: 'business-outline',
        badge: 'Attraction',
      },
      {
        type: 'attraction',
        title: 'Sirao Pictorial Flower Garden',
        subtitle: 'Vibrant celosia blooms and mountain photo spots (Little Amsterdam)',
        icon: 'rose-outline',
        badge: 'Attraction',
      },
      {
        type: 'activity',
        title: 'Historic Fort San Pedro & Magellan’s Cross',
        subtitle: 'Private guided walking tour through 450 years of Cebuano history',
        icon: 'ribbon-outline',
        badge: 'Things to Do',
      },
      {
        type: 'rental',
        title: 'Private Chauffeur Sedan (Toyota Vios)',
        subtitle: 'Full 8-hour air-conditioned private vehicle with all gas & parking included',
        icon: 'car-outline',
        badge: 'Vehicle Rental',
      },
      {
        type: 'perk',
        title: 'All Entrance Fees & Bottled Refreshments',
        subtitle: 'Skip-the-line express admission to all attractions',
        icon: 'sparkles-outline',
        badge: 'Included Perk',
      },
    ],
    exclusions: ['Lunch meals and snacks', 'Personal souvenir purchases'],
    itinerary: [
      {
        day: 1,
        title: 'Full Day Itinerary (8:30 AM – 4:30 PM)',
        description: 'Complete historic colonial district in the morning, followed by cooling mountain heights.',
        highlights: [
          '08:30 AM: Doorstep hotel pickup in comfortable air-conditioned private sedan',
          '09:00 AM: Historic walking tour: Magellan’s Cross, Basilica Minore del Santo Niño & Fort San Pedro',
          '11:30 AM: Ascent into Busay Mountain Ridge with lunch break at a scenic overlook restaurant',
          '01:00 PM: Explore the grand Roman pillars and museum of Temple of Leah',
          '02:30 PM: Walk among colorful celosia blooms and giant hand sculptures at Sirao Garden',
          '04:30 PM: Relaxing descent back to your hotel',
        ],
      },
    ],
    rentalDetail: {
      vehicleType: 'Sedan with Driver',
      model: 'Toyota Vios 1.5G AT',
      duration: '8 Hours (Full Tour Duration)',
      features: ['Air-conditioned comfort', 'Private dedicated driver', 'Fuel and parking fees included'],
      partnerName: 'Cebu City Executive Wheels',
    },
    policy: {
      cancellation: 'Cancel up to 24 hours in advance for a full refund.',
      confirmation: 'Instant booking confirmation.',
      requirements: ['Comfortable walking shoes and camera/phone for stunning photos.'],
    },
  },
  {
    id: 'pkg-bantayan-island-3d2n',
    slug: 'bantayan-island-paradise-3d2n',
    title: '3D2N Bantayan Tropical Paradise: Virgin Island, White Beaches & E-Trike',
    tagline: 'Unwind on northern Cebu’s powdered sands with fresh seafood and island outrigger cruising.',
    description:
      'Step into the laid-back rhythm of Bantayan Island. Featuring powdery white beaches, crystal clear shallows, Virgin Island cliff jumping and snorkeling, 2 nights beachfront in Santa Fe, and your own electric trike / scooter to cruise sleepy fishing villages and sunset cafes.',
    destination: 'Bantayan Island & Santa Fe, North Cebu',
    duration: '3D2N',
    durationDays: 3,
    durationNights: 2,
    category: 'island-escape',
    categoryLabel: 'Tropical Island',
    price: 7650,
    originalPrice: 8900,
    badge: 'Popular',
    featured: false,
    coverImage: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
    localImageKey: 'bantayan',
    gallery: [
      'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
      'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
    ],
    rating: 4.9,
    reviewCount: 22,
    maxGroupSize: 8,
    meetingPoint: 'Santa Fe Port Ferry Terminal, Bantayan Island',
    host: {
      id: 2,
      name: 'Marc Repuesto',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
      role: 'Licensed Regional Tour Guide',
      badge: 'DOT Accredited',
      rating: 4.9,
      reviewCount: 48,
      toursCount: 112,
      bio: 'Expert island hopper and explorer passionate about showcasing Cebu’s untouched natural beauty.',
      responseRate: '100% (within 10 mins)',
      verified: true,
    },
    inclusions: [
      {
        type: 'attraction',
        title: 'Virgin Island Marine Reserve & Cliff Jump',
        subtitle: 'Round-trip private motorized banca cruise and island entry',
        icon: 'boat-outline',
        badge: 'Attraction',
      },
      {
        type: 'activity',
        title: 'Kota Beach Sandbar & Ogtong Cave Exploration',
        subtitle: 'Swim in the natural freshwater cave and relax on the curved sandbar',
        icon: 'sparkles-outline',
        badge: 'Things to Do',
      },
      {
        type: 'accommodation',
        title: 'Amihan Beach Cabanas',
        subtitle: '2 Nights • Beachfront Deluxe Bamboo Cottage with daily breakfast',
        icon: 'bed-outline',
        badge: 'Hotel & Stays',
      },
      {
        type: 'rental',
        title: 'Electric Trike / Island Scooter',
        subtitle: '3-Day rental for effortless island exploration at your own pace',
        icon: 'flash-outline',
        badge: 'Vehicle Rental',
      },
      {
        type: 'perk',
        title: 'Port Greeting & Luggage Transfers',
        subtitle: 'Hassle-free meet-and-greet at Santa Fe port',
        icon: 'checkmark-circle-outline',
        badge: 'Included Perk',
      },
    ],
    exclusions: ['Ferry tickets from Hagnaya to Santa Fe', 'Dinner and personal expenses'],
    itinerary: [
      {
        day: 1,
        title: 'Day 1: Port Welcome, Scooter Pickup & Sunset at Kota Beach',
        description: 'Arrive at Santa Fe port, check in to beach cabana, and witness legendary Bantayan sunset.',
        highlights: [
          'Port meet and greet with Guide Marc’s local team',
          'Check in to your beachfront cabana at Amihan Beach Resort',
          'Pick up your electric scooter or e-trike',
          'Sunset stroll along the world-famous curved sandbar of Kota Beach',
        ],
      },
      {
        day: 2,
        title: 'Day 2: Virgin Island Banca Expedition & Cliff Jumping',
        description: 'Private outrigger boat cruise to Virgin Island for pristine reef snorkeling.',
        highlights: [
          'Board private motorized banca for scenic 20-minute ride to Virgin Island',
          'Swim in powdery crystal shallows and snorkel with clownfish',
          'Optional 20-foot wooden cliff plunge into deep azure waters',
          'Fresh grilled seafood lunch on the island beach',
          'Afternoon dip in Ogtong Cave’s subterranean freshwater spring',
        ],
      },
      {
        day: 3,
        title: 'Day 3: Bantayan Heritage Town, Dried Fish Market & Departure',
        description: 'Morning ride to the centuries-old Saints Peter and Paul Church and port drop-off.',
        highlights: [
          'Ride to Bantayan town proper to visit the 1863 Coral Stone Church',
          'Stop at the famous Bantayan Danggit market for authentic local souvenirs',
          'Return scooter and transfer back to Santa Fe port for your mainland ferry',
        ],
      },
    ],
    accommodationDetail: {
      hotelName: 'Amihan Beach Cabanas',
      roomType: 'Deluxe Beachfront Bamboo Cottage',
      nights: 2,
      amenities: [
        'Direct Step-Out Beach Access',
        'Tropical Artisan Breakfast Included',
        'Air Conditioning & Hot Water',
        'High-Speed Beach Wi-Fi',
      ],
      partnerName: 'Amihan Hospitality',
    },
    rentalDetail: {
      vehicleType: 'E-Scooter / Trike',
      model: 'Island Eco-Scooter 125',
      duration: '3 Full Days',
      features: ['Easy automatic drive', '2 safety helmets included', 'Free recharge at resort'],
      partnerName: 'Bantayan Eco-Wheels',
    },
    policy: {
      cancellation: 'Free cancellation up to 48 hours prior to arrival.',
      confirmation: 'Instant confirmation with guide contact info provided immediately.',
      requirements: ['Beach-ready clothing and waterproof bag for island hopping.'],
    },
  },
];

export async function getPackages(query?: {
  category?: string;
  search?: string;
  minPrice?: number;
  maxPrice?: number;
}): Promise<TravelPackage[]> {
  try {
    // Attempt to fetch from backend API if available
    const searchParams = new URLSearchParams();
    if (query?.category && query.category !== 'all') {
      searchParams.append('category', query.category);
    }
    if (query?.search) {
      searchParams.append('q', query.search);
    }

    const url = `${API_BASE_URL}/api/packages?${searchParams.toString()}`;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 4000);

    const response = await fetch(url, { signal: controller.signal });
    clearTimeout(timeout);

    if (response.ok) {
      const json = await response.json();
      if (Array.isArray(json.packages) && json.packages.length > 0) {
        return json.packages;
      }
    }
  } catch {
    // Graceful fallback to mock data
  }

  // Fallback filter on local data
  let result = [...MOCK_PACKAGES];

  if (query?.category && query.category !== 'all') {
    if (query.category === 'multi-day') {
      result = result.filter((p) => p.durationDays > 1);
    } else if (query.category === 'day-tour') {
      result = result.filter((p) => p.durationDays === 1);
    } else {
      result = result.filter((p) => p.category === query.category);
    }
  }

  if (query?.search && query.search.trim() !== '') {
    const q = query.search.trim().toLowerCase();
    result = result.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.destination.toLowerCase().includes(q) ||
        p.host.name.toLowerCase().includes(q) ||
        p.tagline.toLowerCase().includes(q) ||
        p.description.toLowerCase().includes(q)
    );
  }

  if (query?.minPrice !== undefined) {
    result = result.filter((p) => p.price >= query.minPrice!);
  }
  if (query?.maxPrice !== undefined) {
    result = result.filter((p) => p.price <= query.maxPrice!);
  }

  return result;
}

export async function getPackageById(idOrSlug: string): Promise<TravelPackage | null> {
  try {
    const url = `${API_BASE_URL}/api/packages/${encodeURIComponent(idOrSlug)}`;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 4000);

    const response = await fetch(url, { signal: controller.signal });
    clearTimeout(timeout);

    if (response.ok) {
      const json = await response.json();
      if (json.package) {
        return json.package;
      }
    }
  } catch {
    // Graceful fallback
  }

  const found = MOCK_PACKAGES.find((p) => p.id === idOrSlug || p.slug === idOrSlug);
  return found || null;
}

export async function bookTravelPackage(
  request: PackageBookingRequest
): Promise<PackageBookingResponse> {
  const pkg = await getPackageById(request.packageId);
  const pkgTitle = pkg ? pkg.title : 'Travel Package';
  // Fixed total package price remains unchanged regardless of guests count!
  const price = request.finalTotal ?? (pkg ? pkg.price : 8499);

  try {
    await restoreSession().catch(() => null);
    const path = `/api/packages/${encodeURIComponent(request.packageId)}/book`;
    const res = await api<PackageBookingResponse>(path, {
      method: 'POST',
      body: request,
    });
    if (res && res.success) {
      return res;
    }
  } catch (err) {
    if (err instanceof Error && err.message) {
      throw err;
    }
  }

  const bookingId = `PKG-${Date.now().toString().slice(-6)}`;
  return {
    success: true,
    bookingId,
    packageTitle: pkgTitle,
    totalPrice: price,
    status: 'confirmed',
    travelDate: request.travelDate,
    guestsCount: request.guestsCount,
    guideName: pkg ? pkg.host.name : 'Marc Repuesto',
    message: `Your booking for "${pkgTitle}" has been confirmed! Guide ${pkg ? pkg.host.name : 'Marc'} has received your booking details and will coordinate your itinerary.`,
  };
}
