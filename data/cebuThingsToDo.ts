import { ImageSourcePropType } from 'react-native';

// Famous "Things to do" in Cebu, Philippines.
// Photos are bundled locally (assets/images/places) so they always load
// reliably and instantly, even with a weak connection.

export type ThingToDo = {
  id: string;
  name: string;
  area: string;
  description: string;
  // Full details shown on the place detail screen.
  about: string;
  duration: string;
  priceFrom: number; // starting price in PHP
  highlights: string[];
  image: ImageSourcePropType;
};

export const cebuThingsToDo: ThingToDo[] = [
  {
    id: 'sardine-run-moalboal',
    name: 'Sardine Run',
    area: 'Moalboal',
    description:
      'Snorkel or free-dive just off Panagsama Beach to swim alongside millions of sardines swirling in a giant living tornado — one of the few places on earth you can see it year-round.',
    about:
      'The Moalboal Sardine Run is one of Cebu\u2019s most iconic marine experiences. Just a few meters off Panagsama Beach, millions of sardines gather into a massive, shimmering bait ball that shifts and swirls around you. The reef is shallow and easy to reach, making it perfect for both snorkelers and free-divers. You may also spot sea turtles and colourful corals along the drop-off.',
    duration: '2\u20133 hours',
    priceFrom: 500,
    highlights: [
      'Swim through millions of sardines',
      'Great for snorkelers and free-divers',
      'Chance to see sea turtles',
      'Year-round sightings',
    ],
    image: require('../assets/images/places/sardine.jpg'),
  },
  {
    id: 'kawasan-canyoneering',
    name: 'Kawasan Falls Canyoneering',
    area: 'Badian',
    description:
      'Jump, swim and slide down turquoise canyons before reaching the famous three-tier Kawasan Falls, the most popular canyoneering adventure in the south of Cebu.',
    about:
      'Canyoneering in Badian takes you on an adrenaline-filled journey through turquoise river canyons, with cliff jumps, natural water slides and swims, finishing at the stunning three-tier Kawasan Falls. Guides, helmets and life vests are provided. It\u2019s suitable for beginners with a basic ability to swim, and is one of the most loved adventures in southern Cebu.',
    duration: '4\u20135 hours',
    priceFrom: 1500,
    highlights: [
      'Cliff jumps up to several meters',
      'Natural slides and canyon swims',
      'Guide, helmet and life vest included',
      'Finish at the famous Kawasan Falls',
    ],
    image: require('../assets/images/places/kawasan.jpg'),
  },
  {
    id: 'whale-shark-oslob',
    name: 'Whale Shark Watching',
    area: 'Oslob',
    description:
      'Snorkel beside gentle giant whale sharks (butanding) in the waters of Barangay Tan-awan — an unforgettable, up-close encounter with the world\u2019s largest fish.',
    about:
      'In Barangay Tan-awan, Oslob, you can swim and snorkel near gentle whale sharks (locally called butanding), the largest fish in the world. A short outrigger boat ride brings you to the viewing area where briefing and basic equipment are provided. Early morning is best for calm water and clearer visibility.',
    duration: '1\u20132 hours',
    priceFrom: 1000,
    highlights: [
      'See the world\u2019s largest fish up close',
      'Snorkeling and boat viewing options',
      'Short outrigger boat ride',
      'Best experienced early morning',
    ],
    image: require('../assets/images/places/whaleshark.jpg'),
  },
  {
    id: 'osmena-peak',
    name: 'Osmeña Peak Trek',
    area: 'Dalaguete',
    description:
      'Hike to the highest point in Cebu (about 1,013 m) for sweeping views of jagged, saw-tooth hills and the coastline far below — a short but rewarding sunrise trek.',
    about:
      'Osmeña Peak is the highest point in Cebu at around 1,013 meters above sea level. The short, beginner-friendly trek rewards you with breathtaking panoramic views of jagged, saw-tooth hills that look like a green dragon\u2019s back, with the coastline visible far below. Sunrise treks are especially popular for the cool air and golden light.',
    duration: '1\u20132 hours',
    priceFrom: 300,
    highlights: [
      'Highest point in Cebu (~1,013 m)',
      'Panoramic saw-tooth hill views',
      'Beginner-friendly short hike',
      'Stunning sunrise viewpoint',
    ],
    image: require('../assets/images/places/osmena.jpg'),
  },
  {
    id: 'magellans-cross',
    name: "Magellan's Cross",
    area: 'Cebu City',
    description:
      'Visit the historic cross planted by Ferdinand Magellan in 1521, housed in a chapel beside Basilica del Santo Niño — the symbolic birthplace of Christianity in the Philippines.',
    about:
      'Magellan\u2019s Cross is a Christian cross planted by Portuguese explorer Ferdinand Magellan in 1521, marking the arrival of Christianity in the Philippines. It is housed in a small chapel beside the Basilica Minore del Santo Niño in downtown Cebu City. The ceiling above the cross features a painting depicting the historic event, making it a meaningful and free heritage stop.',
    duration: '30\u201345 minutes',
    priceFrom: 0,
    highlights: [
      'Historic landmark from 1521',
      'Beautiful painted ceiling',
      'Beside Basilica del Santo Niño',
      'Free to visit',
    ],
    image: require('../assets/images/places/magellan.jpg'),
  },
  {
    id: 'temple-of-leah',
    name: 'Temple of Leah',
    area: 'Cebu City',
    description:
      'Explore a grand Roman-inspired temple in the hills of Busay, built as a monument of undying love, with columns, statues and panoramic views over Cebu City.',
    about:
      'Often called the \u201cTaj Mahal of Cebu,\u201d the Temple of Leah is a grand Roman-inspired temple in the hills of Busay, built as a monument of undying love. Visitors can wander among towering columns, lion statues, grand staircases and galleries, while enjoying sweeping views of Cebu City below \u2014 a favourite spot for photos.',
    duration: '1 hour',
    priceFrom: 120,
    highlights: [
      'Grand Roman-inspired architecture',
      'Panoramic views of Cebu City',
      'Great photo spot',
      'Located in cool Busay highlands',
    ],
    image: require('../assets/images/places/templeofleah.jpg'),
  },
  {
    id: 'bantayan-island',
    name: 'Bantayan Island Beaches',
    area: 'Santa Fe',
    description:
      'Relax on powdery white-sand beaches and swim in clear shallow waters on this laid-back northern island, famous for stunning sunsets and a slow island pace.',
    about:
      'Bantayan Island, off the northern tip of Cebu, is known for its powdery white-sand beaches, clear shallow waters and laid-back island vibe. Santa Fe is the main hub, with beautiful resorts, island-hopping trips and unforgettable sunsets. It\u2019s an ideal escape for those who want to slow down and enjoy the sea.',
    duration: 'Full day / overnight',
    priceFrom: 800,
    highlights: [
      'Powdery white-sand beaches',
      'Clear, calm swimming waters',
      'Island-hopping options',
      'Famous sunsets',
    ],
    image: require('../assets/images/places/bantayan.jpg'),
  },
];

export function getThingToDo(id: string): ThingToDo | undefined {
  return cebuThingsToDo.find((t) => t.id === id);
}
