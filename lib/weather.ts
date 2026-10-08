import { Ionicons } from '@expo/vector-icons';

export type WeatherPlace = {
  id: string;
  name: string;
  area: string;
  lat: number;
  lng: number;
};

export const WEATHER_PLACES: WeatherPlace[] = [
  { id: 'cebu', name: 'Cebu City', area: 'Metro Cebu', lat: 10.3157, lng: 123.8854 },
  { id: 'mactan', name: 'Mactan', area: 'Airport & beaches', lat: 10.307, lng: 123.9794 },
  { id: 'moalboal', name: 'Moalboal', area: 'West coast', lat: 9.938, lng: 123.396 },
  { id: 'oslob', name: 'Oslob', area: 'South Cebu', lat: 9.521, lng: 123.395 },
  { id: 'bantayan', name: 'Bantayan', area: 'North island', lat: 11.168, lng: 123.722 },
  { id: 'malapascua', name: 'Malapascua', area: 'North island', lat: 11.348, lng: 124.115 },
];

export type WeatherCurrent = {
  temp: number;
  feelsLike: number;
  humidity: number;
  windKmh: number;
  rainMm: number;
  code: number;
  isDay: boolean;
};

export type WeatherDay = {
  date: string;
  code: number;
  high: number;
  low: number;
  rainChance: number;
  rainMm: number;
};

export type WeatherSnapshot = {
  current: WeatherCurrent;
  daily: WeatherDay[];
};

type OpenMeteoResponse = {
  current?: {
    temperature_2m?: number;
    apparent_temperature?: number;
    relative_humidity_2m?: number;
    wind_speed_10m?: number;
    precipitation?: number;
    weather_code?: number;
    is_day?: number;
  };
  daily?: {
    time?: string[];
    weather_code?: number[];
    temperature_2m_max?: number[];
    temperature_2m_min?: number[];
    precipitation_probability_max?: number[];
    precipitation_sum?: number[];
  };
};

export type WeatherLook = {
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  tip: string;
};

export function weatherLook(code: number, isDay = true): WeatherLook {
  if (code === 0) {
    return {
      label: isDay ? 'Sunny' : 'Clear night',
      icon: isDay ? 'sunny' : 'moon',
      tip: 'Great day for island hopping, diving, or a city walk.',
    };
  }
  if (code <= 3) {
    return {
      label: code === 1 ? 'Mostly clear' : code === 2 ? 'Partly cloudy' : 'Cloudy',
      icon: isDay ? 'partly-sunny' : 'cloudy-night',
      tip: 'Good outdoor weather. Pack light sunscreen just in case.',
    };
  }
  if (code <= 48) {
    return {
      label: 'Foggy',
      icon: 'cloud',
      tip: 'Views may be hazy. Give extra time on mountain roads.',
    };
  }
  if (code <= 57) {
    return {
      label: 'Drizzle',
      icon: 'rainy',
      tip: 'Bring a light raincoat. Waterfalls will be extra full.',
    };
  }
  if (code <= 67) {
    return {
      label: 'Rain',
      icon: 'rainy',
      tip: 'Expect wet roads. Keep ferry and canyon plans flexible.',
    };
  }
  if (code <= 77) {
    return {
      label: 'Wintry mix',
      icon: 'snow',
      tip: 'Unusual for Cebu — dress in layers and check local alerts.',
    };
  }
  if (code <= 82) {
    return {
      label: 'Showers',
      icon: 'rainy',
      tip: 'Short downpours are common. Indoor cafes make a good pause.',
    };
  }
  if (code <= 86) {
    return {
      label: 'Snow showers',
      icon: 'snow',
      tip: 'Unusual for Cebu — check local alerts before heading out.',
    };
  }
  return {
    label: 'Thunderstorms',
    icon: 'thunderstorm',
    tip: 'Stay indoors if lightning starts. Recheck boat and canyon trips.',
  };
}

export function weekdayLabel(isoDate: string): string {
  const [year, month, day] = isoDate.split('-').map(Number);
  const date = new Date(year, (month ?? 1) - 1, day ?? 1);
  const today = new Date();
  if (
    date.getFullYear() === today.getFullYear() &&
    date.getMonth() === today.getMonth() &&
    date.getDate() === today.getDate()
  ) {
    return 'Today';
  }
  return date.toLocaleDateString('en-US', { weekday: 'short' });
}

export async function fetchCebuWeather(place: WeatherPlace): Promise<WeatherSnapshot> {
  const params = new URLSearchParams({
    latitude: String(place.lat),
    longitude: String(place.lng),
    current: 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,precipitation,is_day',
    daily: 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum',
    timezone: 'Asia/Manila',
    forecast_days: '7',
    wind_speed_unit: 'kmh',
  });

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 12000);
  try {
    const res = await fetch(`https://api.open-meteo.com/v1/forecast?${params.toString()}`, {
      signal: controller.signal,
    });
    if (!res.ok) throw new Error('Weather is unavailable right now.');
    const json = (await res.json()) as OpenMeteoResponse;
    const current = json.current;
    if (!current) throw new Error('Weather is unavailable right now.');

    const days: WeatherDay[] = (json.daily?.time ?? []).map((date, i) => ({
      date,
      code: json.daily?.weather_code?.[i] ?? 0,
      high: Math.round(json.daily?.temperature_2m_max?.[i] ?? 0),
      low: Math.round(json.daily?.temperature_2m_min?.[i] ?? 0),
      rainChance: json.daily?.precipitation_probability_max?.[i] ?? 0,
      rainMm: json.daily?.precipitation_sum?.[i] ?? 0,
    }));

    return {
      current: {
        temp: Math.round(current.temperature_2m ?? 0),
        feelsLike: Math.round(current.apparent_temperature ?? 0),
        humidity: Math.round(current.relative_humidity_2m ?? 0),
        windKmh: Math.round(current.wind_speed_10m ?? 0),
        rainMm: current.precipitation ?? 0,
        code: current.weather_code ?? 0,
        isDay: (current.is_day ?? 1) === 1,
      },
      daily: days,
    };
  } finally {
    clearTimeout(timer);
  }
}
