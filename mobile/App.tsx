
import { StatusBar } from 'expo-status-bar';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import * as ImagePicker from 'expo-image-picker';
import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

const API_BASE_URLS = [
  'https://infringement-null-buy-nine.trycloudflare.com/Where2Go/api/mobile',
  'http://192.168.1.3/Where2Go/api/mobile',
];
const PUBLIC_APP_BASE_URL = API_BASE_URLS[0].replace(/\/api\/mobile$/, '');
const inAppLogoLightSource = { uri: `${PUBLIC_APP_BASE_URL}/mobile/assets/InAppLogo1.png?v=20260625-logo-crop` };
const inAppLogoDarkSource = { uri: `${PUBLIC_APP_BASE_URL}/mobile/assets/InAppLogo2.png?v=20260625-logo-crop` };
const AUTH_CUSTOMER_ID_KEY = 'where2go.mobile.customerId';
const AUTH_TOKEN_KEY = 'where2go.mobile.authToken';
const AUTH_ONBOARDING_KEY = 'where2go.mobile.onboardingComplete';
const PROFILE_PHOTO_URI_KEY = 'where2go.mobile.profilePhotoUri';
const SAVED_PLACE_IDS_KEY = 'where2go.mobile.savedPlaceIds';
const THEME_MODE_KEY = 'where2go.mobile.themeMode';

type TabName = 'discover' | 'saved' | 'bookings' | 'profile' | 'settings';
type ThemeMode = 'light' | 'dark';
type LanguageCode = 'en' | 'ar';
type AuthMode = 'login' | 'register';
type ReservationMessageType = 'info' | 'success' | 'error';

type PickForMeAnswers = {
  partySize: number;
  budget: number;
  location: string;
};

type PlaceTheme = {
  preset?: string;
  label?: string;
  accentColor?: string;
  coverImageUrl?: string;
  tagline?: string;
};

type Place = {
  id: string;
  source?: string;
  businessId?: number;
  locationId?: number;
  name: string;
  category: string;
  area: string;
  city: string;
  description: string;
  tags?: string[];
  searchTags?: string;
  imageUrl: string;
  address?: string;
  phone?: string;
  promoCode?: string;
  promoDetails?: string;
  websiteUrl?: string;
  capacityPerHour?: number;
  priceRange: string;
  rating: string;
  reservations: boolean;
  checkins: boolean;
  theme?: PlaceTheme;
  topPick?: boolean;
  topPickSource?: string;
  topPickPosition?: number;
};

type Profile = {
  id: number | null;
  name: string;
  email: string;
  memberSince: string;
  photoUrl?: string;
  middleName?: string;
  lastName?: string;
  age?: number | null;
  phone?: string;
  dateOfBirth?: string;
  address?: string;
  nationality?: string;
};

type ProfileStats = {
  savedPlaces: number;
  bookings: number;
  visits: number;
  rewards: number;
  checkins: number;
};

type ReservationDay = {
  date: string;
  status: 'available' | 'full' | 'closed' | string;
};

type ReservationSlot = {
  time: string;
  label: string;
  available: boolean;
};

type ReservationAvailability = {
  selectedDate: string;
  guests: number;
  calendar: ReservationDay[];
  slots: ReservationSlot[];
  location?: {
    id: number;
    name: string;
    address: string;
    phone: string;
    guestMinimum?: number;
    guestLimit: number;
  };
};

type Booking = {
  id: number;
  locationId: number;
  businessId: number;
  businessName: string;
  category: string;
  address: string;
  phone: string;
  date: string;
  time: string;
  timeLabel: string;
  guests: number;
  status: string;
  createdAt: string;
};

type RewardSummary = {
  total_points: number;
  current_level: number;
  streak: number;
  total_scans: number;
  total_checkins: number;
  today_checkins: number;
  available_rewards: number;
  pending_reward_boxes: number;
  next_threshold?: number;
  progress_percent?: number;
};

type RewardCheckin = {
  id: number;
  businessName: string;
  locationName: string;
  points: number;
  scanType: string;
  checkedInAt: string;
};

type RewardVoucher = {
  id: number;
  businessName: string;
  locationName: string;
  label: string;
  value: number;
  code: string;
  used: boolean;
  expiresAt: string;
};

type RewardBox = {
  id: number;
  businessName: string;
  locationName: string;
  unlockPoints: number;
  triggerLevel: number;
  createdAt: string;
};

type RewardsWallet = {
  summary: RewardSummary;
  checkins: RewardCheckin[];
  vouchers: RewardVoucher[];
  pendingBoxes: RewardBox[];
};

const pickLocationOptions = [
  'Cairo',
  'Giza',
  'Downtown',
  'Islamic Cairo',
  'Coptic Cairo',
  'Zamalek',
  'Heliopolis',
  'Nasr City',
  'Maadi',
  'New Cairo',
  '5th Settlement',
  '1st Settlement',
  'Al-Rehab',
  'Madinaty',
  'El Shorouk',
  'Mokattam',
];

const pickPriceOptions = [
  { label: 'Any', value: '0', helper: 'No limit' },
  { label: '50-100', value: '100', helper: 'EGP' },
  { label: '100-200', value: '200', helper: 'EGP' },
  { label: '200+', value: '300', helper: 'EGP' },
];

const locationAliases: Record<string, string[]> = {
  '5th settlement': ['5th settlement', 'fifth settlement', 'new cairo'],
  '1st settlement': ['1st settlement', 'first settlement', 'new cairo'],
  'al-rehab': ['al rehab', 'rehab', 'new cairo'],
  'al rehab': ['al rehab', 'rehab', 'new cairo'],
  'el shorouk': ['el shorouk', 'shorouk'],
  'new cairo': ['new cairo', 'fifth settlement', '5th settlement', 'first settlement', '1st settlement'],
  downtown: ['downtown', 'abdeen', 'azbakeya', 'cairo'],
  'islamic cairo': ['islamic cairo', 'el mosky', 'al wayli', 'cairo'],
  'coptic cairo': ['coptic cairo', 'old cairo', 'cairo'],
  cairo: ['cairo'],
};

type ApiResult<T> = {
  payload: T;
  baseUrl: string;
};

type MobileApiPayload = {
  ok?: boolean;
  message?: string;
};

type AuthTokenPayload = {
  token?: string;
  expiresAt?: string;
};

const previewPlaces: Place[] = [
  {
    id: 'preview-pyramids',
    name: 'Pyramids of Giza & Sphinx',
    category: 'Heritage',
    area: 'Giza Plateau',
    city: 'Giza',
    description: 'A must-see ancient wonder with the Great Pyramids and Sphinx.',
    imageUrl: '',
    address: 'Giza Plateau, Giza',
    priceRange: '$$$',
    rating: 'Live',
    reservations: false,
    checkins: true,
  },
  {
    id: 'preview-garden-8',
    name: 'Garden 8',
    category: 'Entertainment',
    area: 'New Cairo',
    city: 'Cairo',
    description: 'Open-air restaurants, cafes, and polished evening energy.',
    imageUrl: '',
    address: 'First Settlement, New Cairo',
    priceRange: '$$$',
    rating: 'Live',
    reservations: true,
    checkins: true,
  },
];

const defaultProfile: Profile = {
  id: null,
  name: 'Guest traveler',
  email: 'Sign in to sync your Where2Go profile',
  memberSince: '',
};

const defaultStats: ProfileStats = {
  savedPlaces: 0,
  bookings: 0,
  visits: 0,
  rewards: 0,
  checkins: 0,
};

const defaultRewardsWallet: RewardsWallet = {
  summary: {
    total_points: 0,
    current_level: 0,
    streak: 0,
    total_scans: 0,
    total_checkins: 0,
    today_checkins: 0,
    available_rewards: 0,
    pending_reward_boxes: 0,
  },
  checkins: [],
  vouchers: [],
  pendingBoxes: [],
};

function scorePlaceForTopPicks(place: Place): number {
  const searchableTags = `${place.category} ${place.searchTags || ''} ${(place.tags || []).join(' ')}`.toLowerCase();
  const nightlifeBoost = searchableTags.includes('night') ? 8 : 0;

  return (
    nightlifeBoost
    + (place.reservations ? 3 : 0)
    + (place.checkins ? 2 : 0)
    + (place.promoCode ? 2 : 0)
    + (place.imageUrl ? 1 : 0)
  );
}

function getPlaceThemeAccent(place: Place): string {
  const accentColor = place.theme?.accentColor || '';

  return /^#[0-9a-fA-F]{6}$/.test(accentColor) ? accentColor : '#F26C1C';
}

function hasBusinessTheme(place: Place): boolean {
  return place.source === 'business' && !!place.theme && !!(place.theme.preset || place.theme.tagline || place.theme.coverImageUrl);
}

function getFallbackTopPicks(placeList: Place[]): Place[] {
  return [...placeList]
    .sort((left, right) => scorePlaceForTopPicks(right) - scorePlaceForTopPicks(left))
    .slice(0, 6);
}

function normalizePickText(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

function getPickSearchText(place: Place): string {
  return normalizePickText([
    place.name,
    place.category,
    place.area,
    place.city,
    place.address || '',
    place.description,
    place.searchTags || '',
    (place.tags || []).join(' '),
  ].join(' '));
}

function getPickLocationNeedles(location: string): string[] {
  const normalizedLocation = normalizePickText(location);

  if (!normalizedLocation) {
    return [];
  }

  return locationAliases[normalizedLocation] || [normalizedLocation];
}

function placeMatchesPickLocation(place: Place, location: string): boolean {
  const needles = getPickLocationNeedles(location);

  if (needles.length === 0) {
    return true;
  }

  const haystack = getPickSearchText(place);
  return needles.some((needle) => haystack.includes(normalizePickText(needle)));
}

function getPickPriceBand(place: Place) {
  const dollarCount = (place.priceRange.match(/\$/g) || []).length;
  const lowerPriceRange = place.priceRange.toLowerCase();

  if (dollarCount <= 0) {
    return {
      label: lowerPriceRange.includes('offer') ? 'Offer price' : 'See details',
      min: 0,
      unknown: true,
    };
  }

  if (dollarCount === 1) {
    return { label: '50-100 EGP', min: 50, unknown: false };
  }

  if (dollarCount === 2) {
    return { label: '100-200 EGP', min: 100, unknown: false };
  }

  return { label: '200+ EGP', min: 200, unknown: false };
}

function placeFitsPickParty(place: Place, partySize: number): boolean {
  const party = Number.isFinite(partySize) ? partySize : 2;
  const searchable = getPickSearchText(place);

  if (party >= 6) {
    return /restaurant|entertainment|nightlife|mall|group|dining|club|waterway/.test(searchable);
  }

  if (party <= 2) {
    return /cafe|relaxed|coffee|walk|mall|restaurant|dining/.test(searchable);
  }

  return true;
}

function buildPickForMeScore(place: Place, answers: PickForMeAnswers) {
  const priceBand = getPickPriceBand(place);
  const withinBudget = !answers.budget || priceBand.unknown || priceBand.min <= answers.budget;
  const locationMatched = placeMatchesPickLocation(place, answers.location);
  const partyFit = placeFitsPickParty(place, answers.partySize);
  let score = scorePlaceForTopPicks(place);

  if (locationMatched) {
    score += 8;
  }

  if (withinBudget) {
    score += 5;
  } else if (answers.budget) {
    score -= 4;
  }

  if (partyFit) {
    score += 2;
  }

  return {
    place,
    priceBand,
    withinBudget,
    locationMatched,
    partyFit,
    score,
  };
}

function placeMatchesQuery(place: Place, normalizedQuery: string): boolean {
  if (normalizedQuery === '') {
    return true;
  }

  const searchableText = `${place.name} ${place.category} ${place.area} ${place.city} ${place.description} ${place.searchTags || ''} ${(place.tags || []).join(' ')}`.toLowerCase();
  return searchableText.includes(normalizedQuery);
}

function getCatalogSortIndex(category: string): number {
  const order = ['Nightlife', 'Restaurant', 'Entertainment', 'Activity', 'Cafe', 'Heritage', 'Museum', 'Markets', 'Other'];
  const index = order.findIndex((item) => item.toLowerCase() === category.toLowerCase());

  return index === -1 ? order.length : index;
}

async function fetchMobileJson<T>(path: string, init?: RequestInit): Promise<ApiResult<T>> {
  let lastError: unknown = null;

  for (const baseUrl of API_BASE_URLS) {
    try {
      const response = await fetch(`${baseUrl}/${path}`, init);
      const payload = await response.json().catch(() => null) as (T & MobileApiPayload) | null;

      if (!response.ok || !payload || payload.ok === false) {
        throw new Error(payload?.message || `API responded with ${response.status}`);
      }

      return {
        payload,
        baseUrl,
      };
    } catch (error) {
      lastError = error;
    }
  }

  throw lastError;
}

export default function App() {
  const [places, setPlaces] = useState<Place[]>(previewPlaces);
  const [topPicks, setTopPicks] = useState<Place[]>(getFallbackTopPicks(previewPlaces));
  const [query, setQuery] = useState('');
  const [isPickForMeOpen, setIsPickForMeOpen] = useState(false);
  const [pickPartySize, setPickPartySize] = useState('2');
  const [pickLocation, setPickLocation] = useState('Cairo');
  const [pickPriceRange, setPickPriceRange] = useState('0');
  const [pickMessage, setPickMessage] = useState('');
  const [lastPickForMeId, setLastPickForMeId] = useState('');
  const [activeTab, setActiveTab] = useState<TabName>('discover');
  const [selectedPlace, setSelectedPlace] = useState<Place | null>(null);
  const [savedPlaceIds, setSavedPlaceIds] = useState<string[]>([]);
  const [profile, setProfile] = useState<Profile>(defaultProfile);
  const [profileStats, setProfileStats] = useState<ProfileStats>(defaultStats);
  const [profilePhotoUri, setProfilePhotoUri] = useState('');
  const [profileNotice, setProfileNotice] = useState('');
  const [profileNoticeType, setProfileNoticeType] = useState<ReservationMessageType>('info');
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [bookingMessage, setBookingMessage] = useState('');
  const [bookingMessageType, setBookingMessageType] = useState<ReservationMessageType>('info');
  const [rewardsWallet, setRewardsWallet] = useState<RewardsWallet>(defaultRewardsWallet);
  const [guestCount, setGuestCount] = useState(2);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedSlot, setSelectedSlot] = useState('');
  const [availability, setAvailability] = useState<ReservationAvailability | null>(null);
  const [reservationMessage, setReservationMessage] = useState('');
  const [reservationMessageType, setReservationMessageType] = useState<ReservationMessageType>('info');
  const [isReservationLoading, setIsReservationLoading] = useState(false);
  const [isSubmittingReservation, setIsSubmittingReservation] = useState(false);
  const [themeMode, setThemeMode] = useState<ThemeMode>('light');
  const [language, setLanguage] = useState<LanguageCode>('en');
  const [isAuthGateReady, setIsAuthGateReady] = useState(false);
  const [isAuthGateVisible, setIsAuthGateVisible] = useState(true);
  const [isSkipVisible, setIsSkipVisible] = useState(false);
  const [authMode, setAuthMode] = useState<AuthMode>('register');
  const [authFirstName, setAuthFirstName] = useState('');
  const [authMiddleName, setAuthMiddleName] = useState('');
  const [authLastName, setAuthLastName] = useState('');
  const [authAge, setAuthAge] = useState('');
  const [authPhone, setAuthPhone] = useState('');
  const [authDateOfBirth, setAuthDateOfBirth] = useState('');
  const [authAddress, setAuthAddress] = useState('');
  const [authNationality, setAuthNationality] = useState('');
  const [authEmail, setAuthEmail] = useState('');
  const [authPassword, setAuthPassword] = useState('');
  const [authToken, setAuthToken] = useState('');
  const [authMessage, setAuthMessage] = useState('');
  const [authMessageType, setAuthMessageType] = useState<ReservationMessageType>('info');
  const [isAuthSubmitting, setIsAuthSubmitting] = useState(false);
  const [isScannerVisible, setIsScannerVisible] = useState(false);
  const [isScanSubmitting, setIsScanSubmitting] = useState(false);
  const [lastScannedValue, setLastScannedValue] = useState('');
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();

  colors = themeMode === 'dark' ? darkColors : lightColors;
  styles = createStyles(colors);
  const brandLogoSource = themeMode === 'dark' ? inAppLogoDarkSource : inAppLogoLightSource;

  const getAuthHeaders = (tokenOverride = authToken): Record<string, string> => (
    tokenOverride ? { Authorization: `Bearer ${tokenOverride}` } : {}
  );

  const loadBookings = (customerId?: number | null, tokenOverride = authToken) => {
    if (!customerId) {
      setBookings([]);
      return;
    }

    const customerQuery = customerId ? `&customer_id=${customerId}` : '';

    fetchMobileJson<{ bookings?: Booking[] }>(`reservations.php?action=bookings${customerQuery}`, {
      headers: getAuthHeaders(tokenOverride),
    })
      .then(({ payload }) => setBookings(Array.isArray(payload.bookings) ? payload.bookings : []))
      .catch(() => setBookings([]));
  };

  const saveLocalSavedPlaceIds = async (placeIds: string[]) => {
    await AsyncStorage.setItem(SAVED_PLACE_IDS_KEY, JSON.stringify(Array.from(new Set(placeIds))));
  };

  const loadSavedPlaces = (customerId?: number | null, tokenOverride = authToken) => {
    if (customerId) {
      fetchMobileJson<{ savedPlaceIds?: string[] }>(`saved.php?customer_id=${customerId}`, {
        headers: getAuthHeaders(tokenOverride),
      })
        .then(({ payload }) => {
          const ids = Array.isArray(payload.savedPlaceIds) ? payload.savedPlaceIds : [];
          setSavedPlaceIds(ids);
          saveLocalSavedPlaceIds(ids);
        })
        .catch(() => undefined);
      return;
    }

    AsyncStorage.getItem(SAVED_PLACE_IDS_KEY)
      .then((rawValue) => {
        const parsed = rawValue ? JSON.parse(rawValue) : [];
        setSavedPlaceIds(Array.isArray(parsed) ? parsed.filter((id) => typeof id === 'string') : []);
      })
      .catch(() => setSavedPlaceIds([]));
  };

  const loadRewards = (customerId?: number | null, tokenOverride = authToken) => {
    if (!customerId) {
      setRewardsWallet(defaultRewardsWallet);
      return;
    }

    fetchMobileJson<RewardsWallet>(`rewards.php?customer_id=${customerId}`, {
      headers: getAuthHeaders(tokenOverride),
    })
      .then(({ payload }) => {
        setRewardsWallet({
          summary: payload.summary || defaultRewardsWallet.summary,
          checkins: Array.isArray(payload.checkins) ? payload.checkins : [],
          vouchers: Array.isArray(payload.vouchers) ? payload.vouchers : [],
          pendingBoxes: Array.isArray(payload.pendingBoxes) ? payload.pendingBoxes : [],
        });
      })
      .catch(() => setRewardsWallet(defaultRewardsWallet));
  };

  const loadProfile = (customerId?: number | null, tokenOverride = authToken) => {
    if (!customerId) {
      setProfile(defaultProfile);
      setProfileStats(defaultStats);
      setBookings([]);
      setRewardsWallet(defaultRewardsWallet);
      setProfilePhotoUri('');
      return;
    }

    fetchMobileJson<{ profile?: Profile; stats?: ProfileStats }>(`profile.php?customer_id=${customerId}`, {
      headers: getAuthHeaders(tokenOverride),
    })
      .then(({ payload }) => {
        if (payload.profile) {
          setProfile(payload.profile);
          if (payload.profile.photoUrl) {
            setProfilePhotoUri(payload.profile.photoUrl);
            AsyncStorage.setItem(PROFILE_PHOTO_URI_KEY, payload.profile.photoUrl);
          } else {
            setProfilePhotoUri('');
            AsyncStorage.removeItem(PROFILE_PHOTO_URI_KEY);
          }
          loadBookings(payload.profile.id, tokenOverride);
          loadSavedPlaces(payload.profile.id, tokenOverride);
          loadRewards(payload.profile.id, tokenOverride);
        }

        if (payload.stats) {
          setProfileStats(payload.stats);
        }
      })
      .catch(() => {
        setProfile(defaultProfile);
        setProfileStats(defaultStats);
        setBookings([]);
        setProfilePhotoUri('');
      });
  };

  const loadAvailability = (place: Place, date = selectedDate, guests = guestCount, clearMessage = true) => {
    if (!place.locationId || !place.reservations) {
      setAvailability(null);
      return;
    }

    setIsReservationLoading(true);
    if (clearMessage) {
      setReservationMessage('');
      setReservationMessageType('info');
    }

    const queryParts = [
      `location_id=${encodeURIComponent(String(place.locationId))}`,
      `guests=${encodeURIComponent(String(guests))}`,
    ];

    if (date) {
      queryParts.push(`date=${encodeURIComponent(date)}`);
    }

    fetchMobileJson<ReservationAvailability>(`reservations.php?${queryParts.join('&')}`)
      .then(({ payload }) => {
        setAvailability(payload);
        setSelectedDate(payload.selectedDate || date);
        setGuestCount(payload.guests || guests);
        setSelectedSlot('');
      })
      .catch(() => {
        setAvailability(null);
        setReservationMessageType('error');
        setReservationMessage('Availability could not load right now.');
      })
      .finally(() => setIsReservationLoading(false));
  };

  useEffect(() => {
    let isMounted = true;

    fetchMobileJson<{ places?: Place[]; topPicks?: Place[] }>('places.php')
      .then(({ payload }) => {
        if (!isMounted) {
          return;
        }

        const nextPlaces = Array.isArray(payload.places) ? payload.places : [];
        const nextTopPicks = Array.isArray(payload.topPicks) ? payload.topPicks : [];
        if (nextPlaces.length > 0) {
          setPlaces(nextPlaces);
          setTopPicks(nextTopPicks.length > 0 ? nextTopPicks.slice(0, 6) : getFallbackTopPicks(nextPlaces));
        } else {
          setTopPicks(getFallbackTopPicks(previewPlaces));
        }
      })
      .catch(() => {
        if (isMounted) {
          setTopPicks(getFallbackTopPicks(previewPlaces));
        }
      })

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    let isMounted = true;

    AsyncStorage.multiGet([AUTH_CUSTOMER_ID_KEY, AUTH_TOKEN_KEY, AUTH_ONBOARDING_KEY, PROFILE_PHOTO_URI_KEY, THEME_MODE_KEY])
      .then((entries) => {
        if (!isMounted) {
          return;
        }

        const storedCustomerId = Number(entries[0]?.[1] || 0);
        const storedAuthToken = entries[1]?.[1] || '';
        const onboardingComplete = entries[2]?.[1] === 'done';
        const storedProfilePhotoUri = entries[3]?.[1] || '';
        const storedThemeMode = entries[4]?.[1] as ThemeMode | null;

        if (storedThemeMode === 'dark' || storedThemeMode === 'light') {
          setThemeMode(storedThemeMode);
        }

        if (storedCustomerId > 0 && storedAuthToken) {
          if (storedProfilePhotoUri) {
            setProfilePhotoUri(storedProfilePhotoUri);
          }
          setAuthToken(storedAuthToken);
          setIsAuthGateVisible(false);
          loadProfile(storedCustomerId, storedAuthToken);
          return;
        }

        if (storedProfilePhotoUri) {
          AsyncStorage.removeItem(PROFILE_PHOTO_URI_KEY);
          setProfilePhotoUri('');
        }

        if (storedCustomerId > 0 && !storedAuthToken) {
          AsyncStorage.removeItem(AUTH_CUSTOMER_ID_KEY);
          setAuthToken('');
          loadProfile(null);
          loadSavedPlaces(null);
          setIsAuthGateVisible(true);
          return;
        }

        loadProfile(null);
        loadSavedPlaces(null);
        setIsAuthGateVisible(!onboardingComplete);
      })
      .catch(() => {
        if (isMounted) {
          loadProfile(null);
          loadSavedPlaces(null);
          setIsAuthGateVisible(true);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsAuthGateReady(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (!isAuthGateReady || !isAuthGateVisible) {
      setIsSkipVisible(false);
      return;
    }

    setIsSkipVisible(false);
    const timer = setTimeout(() => setIsSkipVisible(true), 3000);

    return () => clearTimeout(timer);
  }, [isAuthGateReady, isAuthGateVisible]);

  useEffect(() => {
    if (selectedPlace?.reservations && selectedPlace.locationId) {
      loadAvailability(selectedPlace, '', guestCount);
    } else {
      setAvailability(null);
    }
  }, [selectedPlace?.id]);

  const categories = useMemo(() => {
    const uniqueCategories = Array.from(new Set(places.map((place) => place.category).filter(Boolean)));
    return uniqueCategories.sort((left, right) => {
      const leftIndex = getCatalogSortIndex(left);
      const rightIndex = getCatalogSortIndex(right);

      if (leftIndex !== rightIndex) {
        return leftIndex - rightIndex;
      }

      return left.localeCompare(right);
    });
  }, [places]);

  const discoverTopPicks = useMemo(() => {
    const liveTopPicks = topPicks.filter((place) => place && place.id).slice(0, 6);
    return liveTopPicks.length > 0 ? liveTopPicks : getFallbackTopPicks(places);
  }, [places, topPicks]);

  const visiblePlaces = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return places.filter((place) => placeMatchesQuery(place, normalizedQuery));
  }, [places, query]);

  const catalogShelves = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return categories
      .map((category) => ({
        name: category,
        places: places.filter((place) => place.category === category && placeMatchesQuery(place, normalizedQuery)),
      }))
      .filter((shelf) => shelf.places.length > 0);
  }, [categories, places, query]);

  const savedPlaces = useMemo(
    () => places.filter((place) => savedPlaceIds.includes(place.id)),
    [places, savedPlaceIds],
  );

  const openTab = (tabName: TabName) => {
    setSelectedPlace(null);
    setActiveTab(tabName);
  };

  const openAuthGate = (mode: AuthMode = 'login') => {
    setAuthMode(mode);
    setAuthMessage('');
    setAuthMessageType('info');
    setIsAuthGateVisible(true);
  };

  const openPlace = (place: Place, initialGuests = 2) => {
    setSelectedPlace(place);
    setSelectedDate('');
    setSelectedSlot('');
    setReservationMessage('');
    setReservationMessageType('info');
    setGuestCount(Math.max(1, initialGuests));
  };

  const pickForMe = () => {
    if (!isPickForMeOpen) {
      setIsPickForMeOpen(true);
      setPickMessage('');
      return;
    }

    const partyInput = Number.parseInt(pickPartySize, 10);
    const partySize = Number.isFinite(partyInput) ? Math.max(1, Math.min(30, partyInput)) : 2;
    const budgetInput = Number.parseInt(pickPriceRange, 10);
    const answers: PickForMeAnswers = {
      partySize,
      budget: Number.isFinite(budgetInput) && budgetInput > 0 ? budgetInput : 0,
      location: pickLocation,
    };
    const pool = visiblePlaces.length > 0 ? visiblePlaces : places;

    if (pool.length === 0) {
      setPickMessage('No places are ready yet.');
      setIsPickForMeOpen(false);
      return;
    }

    let scoredPlaces = pool
      .filter((place) => place && place.name.trim() !== '')
      .map((place) => buildPickForMeScore(place, answers));
    const budgetMatches = answers.budget ? scoredPlaces.filter((item) => item.withinBudget) : scoredPlaces;

    if (budgetMatches.length > 0) {
      scoredPlaces = budgetMatches;
    }

    const locationMatches = scoredPlaces.filter((item) => item.locationMatched);

    if (locationMatches.length > 0) {
      scoredPlaces = locationMatches;
    }

    if (scoredPlaces.length === 0) {
      setPickMessage('No matching place is ready yet.');
      setIsPickForMeOpen(false);
      return;
    }

    const bestScore = Math.max(...scoredPlaces.map((item) => item.score));
    let topChoices = scoredPlaces.filter((item) => item.score >= bestScore - 2);

    if (topChoices.length > 1 && lastPickForMeId) {
      topChoices = topChoices.filter((item) => item.place.id !== lastPickForMeId);
    }

    const picked = topChoices[Math.floor(Math.random() * topChoices.length)];
    setLastPickForMeId(picked.place.id);
    setPickMessage('');
    setIsPickForMeOpen(false);
    openPlace(picked.place, partySize);
  };

  const skipAuth = async () => {
    setProfile(defaultProfile);
    setProfileStats(defaultStats);
    setBookings([]);
    setRewardsWallet(defaultRewardsWallet);
    setProfilePhotoUri('');
    setAuthToken('');
    loadSavedPlaces(null);
    setIsAuthGateVisible(false);
    setIsSkipVisible(false);
    await AsyncStorage.multiRemove([AUTH_CUSTOMER_ID_KEY, AUTH_TOKEN_KEY, PROFILE_PHOTO_URI_KEY]);
    await AsyncStorage.setItem(AUTH_ONBOARDING_KEY, 'done');
  };

  const submitAuth = async () => {
    const email = authEmail.trim();
    const password = authPassword;
    const firstName = authFirstName.trim();
    const middleName = authMiddleName.trim();
    const lastName = authLastName.trim();
    const age = authAge.trim();
    const phone = authPhone.trim();
    const dateOfBirth = authDateOfBirth.trim();
    const address = authAddress.trim();
    const nationality = authNationality.trim();
    const isRegister = authMode === 'register';

    if (isRegister && firstName === '') {
      setAuthMessageType('error');
      setAuthMessage('First name is required.');
      return;
    }

    if (isRegister && lastName === '') {
      setAuthMessageType('error');
      setAuthMessage('Last name is required.');
      return;
    }

    if (isRegister && (!/^\d{1,3}$/.test(age) || Number(age) < 1 || Number(age) > 120)) {
      setAuthMessageType('error');
      setAuthMessage('Enter a valid age between 1 and 120.');
      return;
    }

    if (isRegister && !/^[0-9+\-\s()]{7,24}$/.test(phone)) {
      setAuthMessageType('error');
      setAuthMessage('Enter a valid phone number.');
      return;
    }

    if (isRegister && !/^\d{4}-\d{2}-\d{2}$/.test(dateOfBirth)) {
      setAuthMessageType('error');
      setAuthMessage('Date of birth must use YYYY-MM-DD.');
      return;
    }

    if (isRegister && address === '') {
      setAuthMessageType('error');
      setAuthMessage('Address is required.');
      return;
    }

    if (isRegister && nationality === '') {
      setAuthMessageType('error');
      setAuthMessage('Nationality is required.');
      return;
    }

    if (!email || !password) {
      setAuthMessageType('error');
      setAuthMessage('Email and password are required.');
      return;
    }

    setIsAuthSubmitting(true);
    setAuthMessage('');
    setAuthMessageType('info');

    try {
      const { payload } = await fetchMobileJson<{
        auth?: AuthTokenPayload;
        message?: string;
        profile?: Profile;
        stats?: ProfileStats;
      }>('auth.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: authMode,
          first_name: firstName,
          middle_name: middleName,
          last_name: lastName,
          age,
          phone,
          date_of_birth: dateOfBirth,
          address,
          nationality,
          email,
          password,
        }),
      });

      if (!payload.profile?.id) {
        throw new Error('Profile could not be loaded.');
      }

      if (!payload.auth?.token) {
        throw new Error('Login succeeded but the secure session token was missing.');
      }

      setProfile(payload.profile);
      setProfileStats(payload.stats || defaultStats);
      setAuthToken(payload.auth.token);
      if (payload.profile.photoUrl) {
        setProfilePhotoUri(payload.profile.photoUrl);
        await AsyncStorage.setItem(PROFILE_PHOTO_URI_KEY, payload.profile.photoUrl);
      } else {
        setProfilePhotoUri('');
        await AsyncStorage.removeItem(PROFILE_PHOTO_URI_KEY);
      }
      loadBookings(payload.profile.id, payload.auth.token);
      loadSavedPlaces(payload.profile.id, payload.auth.token);
      loadRewards(payload.profile.id, payload.auth.token);
      setIsAuthGateVisible(false);
      setIsSkipVisible(false);
      setAuthPassword('');
      if (isRegister) {
        setAuthFirstName('');
        setAuthMiddleName('');
        setAuthLastName('');
        setAuthAge('');
        setAuthPhone('');
        setAuthDateOfBirth('');
        setAuthAddress('');
        setAuthNationality('');
      }
      setAuthMessage('');
      await AsyncStorage.multiSet([
        [AUTH_CUSTOMER_ID_KEY, String(payload.profile.id)],
        [AUTH_TOKEN_KEY, payload.auth.token],
        [AUTH_ONBOARDING_KEY, 'done'],
      ]);
    } catch (error) {
      setAuthMessageType('error');
      setAuthMessage(error instanceof Error ? error.message : 'Login is temporarily unavailable.');
    } finally {
      setIsAuthSubmitting(false);
    }
  };

  const pickProfilePhoto = async () => {
    if (!profile.id || !authToken) {
      setProfileNoticeType('error');
      setProfileNotice('Login or register before adding a profile picture.');
      return;
    }

    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      setProfileNoticeType('error');
      setProfileNotice('Photo library permission is needed to set a profile picture.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.82,
    });

    if (result.canceled || !result.assets?.[0]?.uri) {
      return;
    }

    const nextUri = result.assets[0].uri;
    setProfilePhotoUri(nextUri);
    await AsyncStorage.setItem(PROFILE_PHOTO_URI_KEY, nextUri);

    const formData = new FormData();
    formData.append('customer_id', String(profile.id));
    formData.append('photo', {
      uri: nextUri,
      name: 'profile.jpg',
      type: 'image/jpeg',
    } as unknown as Blob);

    try {
      const { payload } = await fetchMobileJson<{ message?: string; photoUrl?: string }>('profile-photo.php', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: formData,
      });

      if (payload.photoUrl) {
        setProfilePhotoUri(payload.photoUrl);
        await AsyncStorage.setItem(PROFILE_PHOTO_URI_KEY, payload.photoUrl);
      }

      setProfileNoticeType('success');
      setProfileNotice(payload.message || 'Profile picture uploaded.');
    } catch (error) {
      setProfileNoticeType('error');
      setProfileNotice(error instanceof Error ? error.message : 'Profile picture stayed on this device but could not upload.');
    }
  };

  const openQrScanner = async () => {
    if (!profile.id) {
      setProfileNoticeType('error');
      setProfileNotice('Login or register before scanning a place QR code.');
      return;
    }

    const permission = cameraPermission?.granted ? cameraPermission : await requestCameraPermission();

    if (!permission.granted) {
      setProfileNoticeType('error');
      setProfileNotice('Camera permission is needed to scan Where2Go place QR codes.');
      return;
    }

    setProfileNotice('');
    setLastScannedValue('');
    setIsScannerVisible(true);
  };

  const handleQrScanned = async (result: BarcodeScanningResult) => {
    const qrData = result.data || '';

    if (isScanSubmitting || qrData === '' || qrData === lastScannedValue) {
      return;
    }

    if (!profile.id) {
      setIsScannerVisible(false);
      setProfileNoticeType('error');
      setProfileNotice('Login or register before scanning a place QR code.');
      return;
    }

    setLastScannedValue(qrData);
    setIsScanSubmitting(true);

    try {
      const { payload } = await fetchMobileJson<{
        message?: string;
        points_awarded?: number;
        location?: {
          business_name?: string;
          location_name?: string;
          address?: string;
        };
      }>('scan.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...getAuthHeaders(),
        },
        body: JSON.stringify({
          customer_id: profile.id,
          qr_data: qrData,
        }),
      });

      const placeName = payload.location?.business_name || payload.location?.location_name || 'this place';
      setProfileNoticeType('success');
      setProfileNotice(payload.message || `Check-in saved at ${placeName}.`);
      setIsScannerVisible(false);
      loadProfile(profile.id);
      loadRewards(profile.id);
    } catch (error) {
      setProfileNoticeType('error');
      setProfileNotice(error instanceof Error ? error.message : 'This QR code could not be scanned right now.');
      setIsScannerVisible(false);
    } finally {
      setIsScanSubmitting(false);
    }
  };

  const toggleSave = async (place: Place) => {
    const isSaved = savedPlaceIds.includes(place.id);
    const nextSavedIds = isSaved
      ? savedPlaceIds.filter((id) => id !== place.id)
      : [...savedPlaceIds, place.id];

    setSavedPlaceIds(nextSavedIds);
    await saveLocalSavedPlaceIds(nextSavedIds);

    if (!profile.id || (!place.businessId && !place.locationId)) {
      return;
    }

    try {
      const { payload } = await fetchMobileJson<{ savedPlaceIds?: string[] }>('saved.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...getAuthHeaders(),
        },
        body: JSON.stringify({
          action: isSaved ? 'remove' : 'save',
          customer_id: profile.id,
          business_id: place.businessId || 0,
          location_id: place.locationId || 0,
        }),
      });

      if (Array.isArray(payload.savedPlaceIds)) {
        setSavedPlaceIds(payload.savedPlaceIds);
        await saveLocalSavedPlaceIds(payload.savedPlaceIds);
      }
    } catch {
      setSavedPlaceIds(savedPlaceIds);
      await saveLocalSavedPlaceIds(savedPlaceIds);
    }
  };

  const logout = async () => {
    await AsyncStorage.multiRemove([AUTH_CUSTOMER_ID_KEY, AUTH_TOKEN_KEY, PROFILE_PHOTO_URI_KEY]);
    await AsyncStorage.setItem(AUTH_ONBOARDING_KEY, 'done');
    setProfile(defaultProfile);
    setProfileStats(defaultStats);
    setBookings([]);
    setRewardsWallet(defaultRewardsWallet);
    setProfilePhotoUri('');
    setSavedPlaceIds([]);
    setAuthToken('');
    await saveLocalSavedPlaceIds([]);
    setAuthMode('login');
    setAuthPassword('');
    setIsAuthGateVisible(true);
  };

  const changeThemeMode = async (nextThemeMode: ThemeMode) => {
    setThemeMode(nextThemeMode);
    await AsyncStorage.setItem(THEME_MODE_KEY, nextThemeMode);
  };

  const cancelReservation = async (booking: Booking) => {
    if (!profile.id) {
      setBookingMessageType('error');
      setBookingMessage('Login or register before changing reservations.');
      return;
    }

    setBookingMessage('');
    setBookingMessageType('info');

    try {
      const { payload } = await fetchMobileJson<{ message?: string; bookings?: Booking[] }>('reservations.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...getAuthHeaders(),
        },
        body: JSON.stringify({
          action: 'cancel',
          customer_id: profile.id,
          booking_id: booking.id,
        }),
      });

      if (Array.isArray(payload.bookings)) {
        setBookings(payload.bookings);
      } else {
        loadBookings(profile.id);
      }

      setBookingMessageType('success');
      setBookingMessage(payload.message || 'Reservation canceled.');
      loadProfile(profile.id);
    } catch (error) {
      setBookingMessageType('error');
      setBookingMessage(error instanceof Error ? error.message : 'Reservation could not be canceled.');
    }
  };

  const changeGuestCount = (nextGuests: number) => {
    const guestLimit = availability?.location?.guestLimit ?? Math.max(4, (selectedPlace?.capacityPerHour || 20) * 4);
    const guestMinimum = availability?.location?.guestMinimum ?? 1;
    const safeGuests = Math.max(guestMinimum, Math.min(guestLimit, nextGuests));
    setGuestCount(safeGuests);

    if (selectedPlace) {
      loadAvailability(selectedPlace, selectedDate, safeGuests);
    }
  };

  const selectReservationDate = (date: string) => {
    setSelectedDate(date);
    setSelectedSlot('');

    if (selectedPlace) {
      loadAvailability(selectedPlace, date, guestCount);
    }
  };

  const createReservation = () => {
    if (!profile.id) {
      setReservationMessageType('error');
      setReservationMessage('Login or register before sending a reservation request.');
      return;
    }

    if (!selectedPlace?.locationId || !selectedDate || !selectedSlot) {
      setReservationMessageType('error');
      setReservationMessage('Pick a day and time first.');
      return;
    }

    setIsSubmittingReservation(true);
    setReservationMessage('');
    setReservationMessageType('info');

    fetchMobileJson<{ message?: string; bookings?: Booking[] }>('reservations.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...getAuthHeaders(),
      },
      body: JSON.stringify({
        customer_id: profile.id,
        location_id: selectedPlace.locationId,
        date: selectedDate,
        time_slot: selectedSlot,
        guests: guestCount,
      }),
    })
      .then(({ payload }) => {
        setReservationMessageType('success');
        setReservationMessage(payload.message || 'Reservation pending.');
        if (Array.isArray(payload.bookings)) {
          setBookings(payload.bookings);
        } else {
          loadBookings(profile.id);
        }
        loadAvailability(selectedPlace, selectedDate, guestCount, false);
      })
      .catch(() => {
        setReservationMessageType('error');
        setReservationMessage('That time is no longer available. Pick another slot.');
      })
      .finally(() => setIsSubmittingReservation(false));
  };

  const renderPlaceImage = (place: Place, imageStyle = styles.placeImage) => (
    place.imageUrl ? (
      <Image source={{ uri: place.imageUrl }} style={imageStyle} />
    ) : (
      <View style={[imageStyle, styles.imageFallback]}>
        <Text style={styles.imageFallbackText}>{place.name.slice(0, 2).toUpperCase()}</Text>
      </View>
    )
  );

  const renderPlaceCard = (place: Place) => {
    const isSaved = savedPlaceIds.includes(place.id);

    return (
      <View key={place.id} style={styles.placeCard}>
        {renderPlaceImage(place)}
        <View style={styles.placeBody}>
          <View style={styles.placeMetaRow}>
            <Text style={styles.placeCategory}>{place.category}</Text>
            <Text style={styles.placeRating}>{place.rating}</Text>
          </View>
          <Text style={styles.placeName}>{place.name}</Text>
          <Text style={styles.placeArea}>
            {place.area}
            {place.city ? `, ${place.city}` : ''}
          </Text>
          <Text style={styles.placeDescription} numberOfLines={3}>
            {place.description}
          </Text>
          <View style={styles.cardFooter}>
            <Text style={styles.badgeText}>{place.reservations ? 'Reservations' : 'Walk-in'}</Text>
            <Text style={styles.priceText}>{place.priceRange}</Text>
          </View>
          <View style={styles.actionRow}>
            <Pressable style={styles.primaryButton} onPress={() => openPlace(place)}>
              <Text style={styles.primaryButtonText}>View details</Text>
            </Pressable>
            <Pressable
              onPress={() => toggleSave(place)}
              style={[styles.secondaryButton, isSaved && styles.secondaryButtonActive]}
            >
              <Text style={[styles.secondaryButtonText, isSaved && styles.secondaryButtonTextActive]}>
                {isSaved ? 'Saved' : 'Save'}
              </Text>
            </Pressable>
          </View>
        </View>
      </View>
    );
  };

  const renderTopPickCard = (place: Place, index: number) => (
    <Pressable key={`top-pick-${place.id}-${index}`} style={styles.topPickCard} onPress={() => openPlace(place)}>
      {renderPlaceImage(place, styles.topPickImage)}
      <View style={styles.topPickBody}>
        <Text style={styles.topPickKicker}>Top pick #{index + 1}</Text>
        <Text style={styles.topPickName} numberOfLines={2}>{place.name}</Text>
        <Text style={styles.topPickArea} numberOfLines={1}>
          {place.area || place.category}
          {place.city ? `, ${place.city}` : ''}
        </Text>
      </View>
    </Pressable>
  );

  const renderCatalogShelfCard = (place: Place, index: number, catalogName: string) => (
    <Pressable key={`${catalogName}-${place.id}-${index}`} style={styles.topPickCard} onPress={() => openPlace(place)}>
      {renderPlaceImage(place, styles.topPickImage)}
      <View style={styles.topPickBody}>
        <Text style={styles.topPickKicker}>{catalogName}</Text>
        <Text style={styles.topPickName} numberOfLines={2}>{place.name}</Text>
        <Text style={styles.topPickArea} numberOfLines={1}>
          {place.area || place.category}
          {place.city ? `, ${place.city}` : ''}
        </Text>
      </View>
    </Pressable>
  );

  const renderBrandHeader = (subtitle: string) => (
    <View style={styles.header}>
      <View style={styles.brandHeaderLeft}>
        <Image
          key={themeMode === 'dark' ? 'InAppLogo2' : 'InAppLogo1'}
          source={brandLogoSource}
          style={styles.logoImage}
          resizeMode="contain"
        />
        {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
      </View>
      <Pressable style={styles.avatar} onPress={() => openTab('profile')}>
        {profilePhotoUri ? (
          <Image source={{ uri: profilePhotoUri }} style={styles.avatarImage} />
        ) : (
          <Text style={styles.avatarText}>{getInitials(profile.name)}</Text>
        )}
      </Pressable>
    </View>
  );

  const renderAuthGate = () => {
    const isRegister = authMode === 'register';

    if (!isAuthGateReady) {
      return (
        <View style={styles.authScreen}>
          <ActivityIndicator color={colors.accent} />
        </View>
      );
    }

    return (
      <KeyboardAvoidingView
        style={styles.authScreen}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={0}
      >
        {isSkipVisible ? (
          <Pressable style={styles.authSkipButton} onPress={skipAuth}>
            <Text style={styles.authSkipText}>Skip</Text>
          </Pressable>
        ) : null}

        <ScrollView
          contentContainerStyle={styles.authContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          <Image source={brandLogoSource} style={styles.authLogo} resizeMode="contain" />
          <Text style={styles.authTitle}>Start your Where2Go account</Text>
          <Text style={styles.authSubtitle}>Register to save places, send reservations, and keep bookings connected to you.</Text>

          <View style={styles.authCard}>
            <View style={styles.segmentedControl}>
              <Pressable
                style={[styles.segmentButton, isRegister && styles.segmentButtonActive]}
                onPress={() => {
                  setAuthMode('register');
                  setAuthMessage('');
                }}
              >
                <Text style={[styles.segmentText, isRegister && styles.segmentTextActive]}>Register</Text>
              </Pressable>
              <Pressable
                style={[styles.segmentButton, !isRegister && styles.segmentButtonActive]}
                onPress={() => {
                  setAuthMode('login');
                  setAuthMessage('');
                }}
              >
                <Text style={[styles.segmentText, !isRegister && styles.segmentTextActive]}>Login</Text>
              </Pressable>
            </View>

            {isRegister ? (
              <>
                <View style={styles.authNameRow}>
                  <TextInput
                    value={authFirstName}
                    onChangeText={setAuthFirstName}
                    placeholder="First name"
                    placeholderTextColor={colors.muted}
                    autoCapitalize="words"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                  <TextInput
                    value={authMiddleName}
                    onChangeText={setAuthMiddleName}
                    placeholder="Middle name"
                    placeholderTextColor={colors.muted}
                    autoCapitalize="words"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                </View>
                <View style={styles.authNameRow}>
                  <TextInput
                    value={authLastName}
                    onChangeText={setAuthLastName}
                    placeholder="Last name"
                    placeholderTextColor={colors.muted}
                    autoCapitalize="words"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                  <TextInput
                    value={authAge}
                    onChangeText={setAuthAge}
                    placeholder="Age"
                    placeholderTextColor={colors.muted}
                    keyboardType="number-pad"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                </View>
                <View style={styles.authNameRow}>
                  <TextInput
                    value={authPhone}
                    onChangeText={setAuthPhone}
                    placeholder="Phone number"
                    placeholderTextColor={colors.muted}
                    keyboardType="phone-pad"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                  <TextInput
                    value={authNationality}
                    onChangeText={setAuthNationality}
                    placeholder="Nationality"
                    placeholderTextColor={colors.muted}
                    autoCapitalize="words"
                    style={[styles.searchInput, styles.authInput, styles.authNameInput]}
                  />
                </View>
                <TextInput
                  value={authDateOfBirth}
                  onChangeText={setAuthDateOfBirth}
                  placeholder="Date of birth (YYYY-MM-DD)"
                  placeholderTextColor={colors.muted}
                  keyboardType="numbers-and-punctuation"
                  style={[styles.searchInput, styles.authInput]}
                />
                <TextInput
                  value={authAddress}
                  onChangeText={setAuthAddress}
                  placeholder="Address"
                  placeholderTextColor={colors.muted}
                  autoCapitalize="words"
                  style={[styles.searchInput, styles.authInput]}
                />
              </>
            ) : null}

            <TextInput
              value={authEmail}
              onChangeText={setAuthEmail}
              placeholder="Email address"
              placeholderTextColor={colors.muted}
              keyboardType="email-address"
              autoCapitalize="none"
              style={[styles.searchInput, styles.authInput]}
            />
            <TextInput
              value={authPassword}
              onChangeText={setAuthPassword}
              placeholder="Password"
              placeholderTextColor={colors.muted}
              secureTextEntry
              style={[styles.searchInput, styles.authInput]}
            />

            {authMessage ? (
              <View style={[styles.reservationNotice, authMessageType === 'error' && styles.reservationNoticeError]}>
                <Text style={[styles.reservationNoticeText, authMessageType === 'error' && styles.reservationNoticeTextError]}>
                  {authMessage}
                </Text>
              </View>
            ) : null}

            <Pressable
              disabled={isAuthSubmitting}
              style={[styles.primaryButton, isAuthSubmitting && styles.disabledButton]}
              onPress={submitAuth}
            >
              <Text style={styles.primaryButtonText}>
                {isAuthSubmitting ? 'Please wait...' : isRegister ? 'Create account' : 'Login'}
              </Text>
            </Pressable>

            <Pressable
              style={styles.authSwitchButton}
              onPress={() => {
                setAuthMode(isRegister ? 'login' : 'register');
                setAuthMessage('');
              }}
            >
              <Text style={styles.authSwitchText}>
                {isRegister ? 'Already have an account? Login' : 'New here? Create an account'}
              </Text>
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    );
  };

  const renderQrScanner = () => (
    <View style={styles.scannerScreen}>
      <CameraView
        style={styles.cameraPreview}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={isScanSubmitting ? undefined : handleQrScanned}
      >
        <View style={styles.scannerOverlay}>
          <Pressable style={styles.scannerCloseButton} onPress={() => setIsScannerVisible(false)}>
            <Text style={styles.scannerCloseText}>Close</Text>
          </Pressable>
          <View style={styles.scannerFrame} />
          <Text style={styles.scannerTitle}>Scan place QR</Text>
          <Text style={styles.scannerText}>Point the camera at a Where2Go QR code at the business.</Text>
          {isScanSubmitting ? (
            <View style={styles.scannerLoading}>
              <ActivityIndicator color={colors.onDark} />
              <Text style={styles.scannerText}>Saving check-in...</Text>
            </View>
          ) : null}
        </View>
      </CameraView>
    </View>
  );

  const renderPickForMePanel = () => {
    if (!isPickForMeOpen && !pickMessage) {
      return null;
    }

    return (
      <View style={styles.pickPanel}>
        {isPickForMeOpen ? (
          <>
            <View style={styles.pickPanelHeader}>
              <View>
                <Text style={styles.pickPanelTitle}>Pick for me</Text>
                <Text style={styles.pickPanelSubtitle}>Same idea as the website: people, location, and price range.</Text>
              </View>
              <Pressable onPress={() => setIsPickForMeOpen(false)} style={styles.pickPanelClose}>
                <Text style={styles.pickPanelCloseText}>Close</Text>
              </Pressable>
            </View>

            <View style={styles.pickField}>
              <Text style={styles.pickFieldLabel}>How many people?</Text>
              <TextInput
                value={pickPartySize}
                onChangeText={(value) => setPickPartySize(value.replace(/[^0-9]/g, '').slice(0, 2))}
                keyboardType="number-pad"
                placeholder="2"
                placeholderTextColor={colors.muted}
                style={styles.pickInput}
              />
            </View>

            <View style={styles.pickField}>
              <Text style={styles.pickFieldLabel}>Location</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.pickChipRow}>
                {pickLocationOptions.map((location) => {
                  const isSelected = pickLocation === location;

                  return (
                    <Pressable
                      key={location}
                      onPress={() => setPickLocation(location)}
                      style={[styles.pickChip, isSelected && styles.pickChipSelected]}
                    >
                      <Text style={[styles.pickChipText, isSelected && styles.pickChipTextSelected]}>{location}</Text>
                    </Pressable>
                  );
                })}
              </ScrollView>
            </View>

            <View style={styles.pickField}>
              <Text style={styles.pickFieldLabel}>Price range / person</Text>
              <View style={styles.pickPriceGrid}>
                {pickPriceOptions.map((option) => {
                  const isSelected = pickPriceRange === option.value;

                  return (
                    <Pressable
                      key={option.value}
                      onPress={() => setPickPriceRange(option.value)}
                      style={[styles.pickPriceCard, isSelected && styles.pickChipSelected]}
                    >
                      <Text style={[styles.pickChipText, isSelected && styles.pickChipTextSelected]}>{option.label}</Text>
                      <Text style={[styles.pickPriceHelper, isSelected && styles.pickChipTextSelected]}>{option.helper}</Text>
                    </Pressable>
                  );
                })}
              </View>
            </View>

            <Pressable style={styles.pickFindButton} onPress={pickForMe}>
              <Text style={styles.pickFindButtonText}>Find a place</Text>
            </Pressable>
          </>
        ) : (
          <Text style={styles.pickPanelSubtitle}>{pickMessage}</Text>
        )}
      </View>
    );
  };

  const renderDiscover = () => (
    <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {renderBrandHeader('')}

      <View style={styles.searchRow}>
        <TextInput
          value={query}
          onChangeText={setQuery}
          placeholder="Search places, areas, moods"
          placeholderTextColor={colors.muted}
          style={[styles.searchInput, styles.discoverSearchInput]}
        />
        <Pressable style={styles.pickForMeButton} onPress={pickForMe}>
          <Text style={styles.pickForMeText}>{isPickForMeOpen ? 'Find place' : 'Pick for me'}</Text>
        </Pressable>
      </View>

      {renderPickForMePanel()}

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Top Picks for today</Text>
        <Text style={styles.sectionCount}>{discoverTopPicks.length} picks</Text>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.topPicksRow}>
        {discoverTopPicks.map(renderTopPickCard)}
      </ScrollView>

      {catalogShelves.map((shelf) => (
        <View key={shelf.name} style={styles.catalogShelf}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>{shelf.name}</Text>
            <Text style={styles.sectionCount}>
              {shelf.places.length} {shelf.places.length === 1 ? 'place' : 'places'}
            </Text>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.topPicksRow}>
            {shelf.places.map((place, index) => renderCatalogShelfCard(place, index, shelf.name))}
          </ScrollView>
        </View>
      ))}

      {catalogShelves.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No places matched</Text>
          <Text style={styles.emptyText}>Try a broader search like nightlife, cafe, activity, or an area name.</Text>
        </View>
      ) : null}
    </ScrollView>
  );

  const renderSaved = () => (
    <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.pageHeader}>
        <Text style={styles.pageTitle}>Saved places</Text>
        <Text style={styles.pageSubtitle}>Keep your short list ready for later plans.</Text>
      </View>

      {savedPlaces.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No saved places yet</Text>
          <Text style={styles.emptyText}>Save a place from Discover and it will appear here.</Text>
          <Pressable style={styles.primaryButton} onPress={() => openTab('discover')}>
            <Text style={styles.primaryButtonText}>Explore places</Text>
          </Pressable>
        </View>
      ) : (
        savedPlaces.map(renderPlaceCard)
      )}
    </ScrollView>
  );

  const renderBookings = () => (
    <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.pageHeader}>
        <Text style={styles.pageTitle}>Bookings</Text>
        <Text style={styles.pageSubtitle}>Reservation requests from the mobile app and website live here.</Text>
      </View>

      {bookingMessage ? (
        <View
          style={[
            styles.reservationNotice,
            bookingMessageType === 'success' && styles.reservationNoticeSuccess,
            bookingMessageType === 'error' && styles.reservationNoticeError,
            styles.profileNotice,
          ]}
        >
          <Text
            style={[
              styles.reservationNoticeText,
              bookingMessageType === 'success' && styles.reservationNoticeTextSuccess,
              bookingMessageType === 'error' && styles.reservationNoticeTextError,
            ]}
          >
            {bookingMessage}
          </Text>
        </View>
      ) : null}

      {bookings.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No reservations yet</Text>
          <Text style={styles.emptyText}>Open a reservable place and send your first request.</Text>
          <Pressable style={styles.primaryButton} onPress={() => openTab('discover')}>
            <Text style={styles.primaryButtonText}>Find a place</Text>
          </Pressable>
        </View>
      ) : (
        bookings.map((booking) => (
          <View key={booking.id} style={styles.bookingCard}>
            <View style={styles.placeMetaRow}>
              <Text style={styles.placeCategory}>{booking.status}</Text>
              <Text style={styles.placeRating}>{booking.timeLabel}</Text>
            </View>
            <Text style={styles.placeName}>{booking.businessName}</Text>
            <Text style={styles.placeArea}>{formatFullDate(booking.date)} - {booking.guests} guest{booking.guests === 1 ? '' : 's'}</Text>
            <Text style={styles.placeDescription}>{booking.address || 'Location details on partner listing.'}</Text>
            {['pending', 'confirmed'].includes(booking.status.toLowerCase()) ? (
              <Pressable style={[styles.secondaryButton, styles.bookingCancelButton]} onPress={() => cancelReservation(booking)}>
                <Text style={styles.secondaryButtonText}>Cancel reservation</Text>
              </Pressable>
            ) : null}
          </View>
        ))
      )}
    </ScrollView>
  );

  const renderProfile = () => (
    <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.profileCard}>
        <Pressable style={styles.profileAvatar} onPress={pickProfilePhoto}>
          {profilePhotoUri ? (
            <Image source={{ uri: profilePhotoUri }} style={styles.profileAvatarImage} />
          ) : (
            <Text style={styles.profileAvatarText}>{getInitials(profile.name)}</Text>
          )}
        </Pressable>
        <View style={styles.profileInfo}>
          <Text style={styles.profileName}>{profile.name}</Text>
          <Text style={styles.profileEmail}>{profile.email}</Text>
          <Text style={styles.profileNote}>
            {profile.memberSince ? `Member since ${formatDate(profile.memberSince)}` : 'Mobile account sync coming next.'}
          </Text>
        </View>
      </View>

      <LevelProgressBar summary={rewardsWallet.summary} />

      {profileNotice ? (
        <View
          style={[
            styles.reservationNotice,
            profileNoticeType === 'success' && styles.reservationNoticeSuccess,
            profileNoticeType === 'error' && styles.reservationNoticeError,
            styles.profileNotice,
          ]}
        >
          <Text
            style={[
              styles.reservationNoticeText,
              profileNoticeType === 'success' && styles.reservationNoticeTextSuccess,
              profileNoticeType === 'error' && styles.reservationNoticeTextError,
            ]}
          >
            {profileNotice}
          </Text>
        </View>
      ) : null}

      <View style={styles.profileQuickActions}>
        <Pressable style={[styles.secondaryButton, styles.profileQuickButton]} onPress={pickProfilePhoto}>
          <Text style={styles.secondaryButtonText}>
            {!profile.id ? 'Login to add photo' : profilePhotoUri ? 'Change photo' : 'Add profile photo'}
          </Text>
        </Pressable>
        <Pressable style={[styles.primaryButton, styles.profileQuickButton]} onPress={openQrScanner}>
          <Text style={styles.primaryButtonText}>Scan place QR</Text>
        </Pressable>
      </View>

      <View style={styles.statsGrid}>
        <StatBox label="Saved" value={savedPlaces.length || profileStats.savedPlaces} />
        <StatBox label="Bookings" value={bookings.length || profileStats.bookings} />
        <StatBox label="Visits" value={profileStats.visits} />
        <StatBox label="Rewards" value={rewardsWallet.summary.available_rewards || profileStats.rewards} />
      </View>

      <View style={styles.profileSection}>
        <Text style={styles.profileSectionTitle}>Rewards wallet</Text>
        <View style={styles.rewardMetricGrid}>
          <StatBox label="Points" value={rewardsWallet.summary.total_points} />
          <StatBox label="Level" value={rewardsWallet.summary.current_level} />
          <StatBox label="Streak" value={`${rewardsWallet.summary.streak} day${rewardsWallet.summary.streak === 1 ? '' : 's'}`} />
          <StatBox label="Boxes" value={rewardsWallet.summary.pending_reward_boxes} />
        </View>

        {rewardsWallet.checkins.length > 0 ? (
          <View style={styles.rewardList}>
            <Text style={styles.profileActionTitle}>Recent scans</Text>
            {rewardsWallet.checkins.map((checkin) => (
              <Text key={checkin.id} style={styles.profileActionText}>
                {checkin.businessName} - +{checkin.points} points
              </Text>
            ))}
          </View>
        ) : (
          <Text style={styles.profileActionText}>Scan a partner QR code to start earning points.</Text>
        )}

        {rewardsWallet.vouchers.length > 0 ? (
          <View style={styles.rewardList}>
            <Text style={styles.profileActionTitle}>Vouchers</Text>
            {rewardsWallet.vouchers.map((voucher) => (
              <Text key={voucher.id} style={styles.profileActionText}>
                {voucher.label} at {voucher.businessName}{voucher.code ? ` - ${voucher.code}` : ''}
              </Text>
            ))}
          </View>
        ) : null}
      </View>

      <View style={styles.profileSection}>
        <Text style={styles.profileSectionTitle}>Profile tools</Text>
        {!profile.id ? (
          <Pressable style={styles.primaryButton} onPress={() => openAuthGate('register')}>
            <Text style={styles.primaryButtonText}>Login or register</Text>
          </Pressable>
        ) : null}
        <ProfileAction title="Booking history" text={`${bookings.length || profileStats.bookings} reservation requests connected to this account.`} />
        <ProfileAction title="Rewards wallet" text={`${rewardsWallet.summary.total_checkins || profileStats.checkins} check-ins tracked so far.`} />
        <ProfileAction title="Saved places" text={`${savedPlaces.length || profileStats.savedPlaces} places saved on this device.`} />
        <ProfileAction title="Account details" text="Name, email, photo, and preferences come after login sync." />
        {profile.id ? (
          <Pressable style={[styles.secondaryButton, styles.dangerButton]} onPress={logout}>
            <Text style={[styles.secondaryButtonText, styles.dangerButtonText]}>Logout</Text>
          </Pressable>
        ) : null}
      </View>
    </ScrollView>
  );

  const renderSettings = () => {
    const isDarkMode = themeMode === 'dark';

    return (
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.pageHeader}>
          <Text style={styles.pageTitle}>Settings</Text>
          <Text style={styles.pageSubtitle}>Control app appearance and language preferences.</Text>
        </View>

        <View style={styles.settingsCard}>
          <View style={styles.settingsHeader}>
            <View style={styles.settingsIcon}>
              <Text style={styles.settingsIconText}>A</Text>
            </View>
            <View style={styles.settingsCopy}>
              <Text style={styles.settingsTitle}>Language</Text>
              <Text style={styles.settingsText}>English is active. Arabic is listed for the next translation pass.</Text>
            </View>
          </View>

          <View style={styles.segmentedControl}>
            <Pressable
              style={[styles.segmentButton, language === 'en' && styles.segmentButtonActive]}
              onPress={() => setLanguage('en')}
            >
              <Text style={[styles.segmentText, language === 'en' && styles.segmentTextActive]}>English</Text>
            </Pressable>
            <Pressable
              disabled
              style={[styles.segmentButton, styles.segmentButtonDisabled]}
              onPress={() => setLanguage('ar')}
            >
              <Text style={styles.segmentText}>Arabic soon</Text>
            </Pressable>
          </View>
        </View>

        <View style={styles.settingsCard}>
          <View style={styles.settingsHeader}>
            <View style={styles.settingsIcon}>
              <Text style={styles.settingsIconText}>{isDarkMode ? 'D' : 'L'}</Text>
            </View>
            <View style={styles.settingsCopy}>
              <Text style={styles.settingsTitle}>Dark mode</Text>
              <Text style={styles.settingsText}>{isDarkMode ? 'Dark mode is on.' : 'Use a darker theme for lower-light browsing.'}</Text>
            </View>
            <Pressable
              accessibilityRole="switch"
              accessibilityState={{ checked: isDarkMode }}
              onPress={() => changeThemeMode(isDarkMode ? 'light' : 'dark')}
              style={[styles.switchTrack, isDarkMode && styles.switchTrackActive]}
            >
              <View style={[styles.switchThumb, isDarkMode && styles.switchThumbActive]} />
            </Pressable>
          </View>
        </View>
      </ScrollView>
    );
  };

  const renderReservationPanel = (place: Place) => {
    const themeAccent = hasBusinessTheme(place) ? getPlaceThemeAccent(place) : colors.accent;

    if (!place.reservations || !place.locationId) {
      return (
        <View style={styles.reservationPanel}>
          <Text style={styles.detailSectionTitle}>Reservation</Text>
          <Text style={styles.detailSectionText}>Reservations are not available for this place right now.</Text>
        </View>
      );
    }

    return (
      <View style={styles.reservationPanel}>
        <View style={styles.sectionHeader}>
          <Text style={styles.detailSectionTitle}>Reserve a visit</Text>
          {isReservationLoading ? <ActivityIndicator color={colors.accent} /> : null}
        </View>

        <View style={styles.guestRow}>
          <View>
            <Text style={styles.guestLabel}>Guests</Text>
            {availability?.location?.guestMinimum && availability.location.guestMinimum > 1 ? (
              <Text style={styles.detailSectionText}>Minimum {availability.location.guestMinimum}, max {availability.location.guestLimit}</Text>
            ) : null}
          </View>
          <View style={styles.stepper}>
            <Pressable style={styles.stepButton} onPress={() => changeGuestCount(guestCount - 1)}>
              <Text style={styles.stepText}>-</Text>
            </Pressable>
            <Text style={styles.guestCount}>{guestCount}</Text>
            <Pressable style={styles.stepButton} onPress={() => changeGuestCount(guestCount + 1)}>
              <Text style={styles.stepText}>+</Text>
            </Pressable>
          </View>
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.dayRow}>
          {(availability?.calendar || []).map((day) => {
            const isSelected = selectedDate === day.date;
            const isAvailable = day.status === 'available';

            return (
              <Pressable
                key={day.date}
                disabled={!isAvailable}
                onPress={() => selectReservationDate(day.date)}
                style={[
                  styles.dayCard,
                  isSelected && styles.dayCardSelected,
                  !isAvailable && styles.dayCardDisabled,
                ]}
              >
                <Text style={[styles.dayName, isSelected && styles.dayTextSelected]}>{formatDayName(day.date)}</Text>
                <Text style={[styles.dayNumber, isSelected && styles.dayTextSelected]}>{formatDayNumber(day.date)}</Text>
                <Text style={[styles.dayStatus, isSelected && styles.dayTextSelected]}>{day.status}</Text>
              </Pressable>
            );
          })}
        </ScrollView>

        <View style={styles.slotGrid}>
          {(availability?.slots || []).map((slot) => {
            const isSelected = selectedSlot === slot.time;

            return (
              <Pressable
                key={slot.time}
                disabled={!slot.available}
                onPress={() => setSelectedSlot(slot.time)}
                style={[
                  styles.slotPill,
                  isSelected && styles.slotPillSelected,
                  !slot.available && styles.slotPillDisabled,
                ]}
              >
                <Text style={[styles.slotText, isSelected && styles.slotTextSelected]}>{slot.label}</Text>
              </Pressable>
            );
          })}
        </View>

        {reservationMessage ? (
          <View
            style={[
              styles.reservationNotice,
              reservationMessageType === 'success' && styles.reservationNoticeSuccess,
              reservationMessageType === 'error' && styles.reservationNoticeError,
            ]}
          >
            <Text
              style={[
                styles.reservationNoticeText,
                reservationMessageType === 'success' && styles.reservationNoticeTextSuccess,
                reservationMessageType === 'error' && styles.reservationNoticeTextError,
              ]}
            >
              {reservationMessage}
            </Text>
          </View>
        ) : null}

        <Pressable
          disabled={isSubmittingReservation || !selectedSlot}
          style={[styles.primaryButton, { backgroundColor: themeAccent }, (!selectedSlot || isSubmittingReservation) && styles.disabledButton]}
          onPress={createReservation}
        >
          <Text style={styles.primaryButtonText}>{isSubmittingReservation ? 'Sending...' : 'Send reservation request'}</Text>
        </Pressable>
      </View>
    );
  };

  const renderDetails = (place: Place) => {
    const isSaved = savedPlaceIds.includes(place.id);
    const placeHasTheme = hasBusinessTheme(place);
    const themeAccent = placeHasTheme ? getPlaceThemeAccent(place) : colors.accent;
    const themeLabel = place.theme?.label || 'Business style';
    const themeTagline = (place.theme?.tagline || '').trim();

    return (
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <Pressable style={styles.backButton} onPress={() => setSelectedPlace(null)}>
          <Text style={styles.backButtonText}>Back</Text>
        </Pressable>

        <View style={[styles.detailCard, placeHasTheme && styles.detailCardThemed, placeHasTheme && { borderColor: themeAccent }]}>
          {renderPlaceImage(place, styles.detailImage)}
          <View style={styles.detailBody}>
            <View style={styles.placeMetaRow}>
              <Text style={[styles.placeCategory, placeHasTheme && { color: themeAccent }]}>{place.category}</Text>
              <Text style={[styles.priceText, placeHasTheme && { color: themeAccent }]}>{place.priceRange}</Text>
            </View>
            <Text style={styles.detailTitle}>{place.name}</Text>
            {themeTagline ? <Text style={[styles.businessThemeTagline, { color: themeAccent }]}>{themeTagline}</Text> : null}
            {placeHasTheme && place.theme?.preset && place.theme.preset !== 'where2go' ? (
              <View style={[styles.businessThemeBadge, { borderColor: themeAccent }]}>
                <Text style={[styles.businessThemeBadgeText, { color: themeAccent }]}>{themeLabel}</Text>
              </View>
            ) : null}
            <Text style={styles.placeArea}>
              {place.area}
              {place.city ? `, ${place.city}` : ''}
            </Text>
            <Text style={styles.detailDescription}>{place.description}</Text>

            <View style={styles.infoGrid}>
              <InfoPill label="Reservations" value={place.reservations ? 'Available' : 'Walk-in'} />
              <InfoPill label="Check-in" value={place.checkins ? 'Enabled' : 'Soon'} />
              <InfoPill label="Rating" value={place.rating} />
              <InfoPill label="Contact" value={place.phone || 'On listing'} />
            </View>

            {place.address ? (
              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Address</Text>
                <Text style={styles.detailSectionText}>{place.address}</Text>
              </View>
            ) : null}

            {place.promoCode || place.promoDetails ? (
              <View style={styles.promoBox}>
                <Text style={styles.promoTitle}>{place.promoCode ? `Promo: ${place.promoCode}` : 'Promo available'}</Text>
                <Text style={styles.promoText}>{place.promoDetails || 'Ask the partner for the current Where2Go offer.'}</Text>
              </View>
            ) : null}

            {renderReservationPanel(place)}

            <View style={styles.detailActions}>
              <Pressable
                style={[styles.secondaryButton, isSaved && styles.secondaryButtonActive]}
                onPress={() => toggleSave(place)}
              >
                <Text style={[styles.secondaryButtonText, isSaved && styles.secondaryButtonTextActive]}>
                  {isSaved ? 'Saved' : 'Save place'}
                </Text>
              </Pressable>
              <Pressable style={styles.secondaryButton} onPress={() => openTab('bookings')}>
                <Text style={styles.secondaryButtonText}>My bookings</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </ScrollView>
    );
  };

  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style={themeMode === 'dark' ? 'light' : 'dark'} />
        {isScannerVisible ? (
          renderQrScanner()
        ) : !isAuthGateReady || isAuthGateVisible ? (
          renderAuthGate()
        ) : (
          <View style={styles.appShell}>
            <View style={styles.screen}>
              {selectedPlace ? renderDetails(selectedPlace) : null}
              {!selectedPlace && activeTab === 'discover' ? renderDiscover() : null}
              {!selectedPlace && activeTab === 'saved' ? renderSaved() : null}
              {!selectedPlace && activeTab === 'bookings' ? renderBookings() : null}
              {!selectedPlace && activeTab === 'profile' ? renderProfile() : null}
              {!selectedPlace && activeTab === 'settings' ? renderSettings() : null}
            </View>
            <View style={styles.tabBar}>
              <TabButton label="Discover" isActive={activeTab === 'discover' && !selectedPlace} onPress={() => openTab('discover')} />
              <TabButton label="Saved" isActive={activeTab === 'saved' && !selectedPlace} onPress={() => openTab('saved')} />
              <TabButton label="Bookings" isActive={activeTab === 'bookings' && !selectedPlace} onPress={() => openTab('bookings')} />
              <TabButton label="Profile" isActive={activeTab === 'profile' && !selectedPlace} onPress={() => openTab('profile')} />
              <TabButton label="Settings" isActive={activeTab === 'settings' && !selectedPlace} onPress={() => openTab('settings')} />
            </View>
          </View>
        )}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const lightColors = {
  page: '#fffaf5',
  surface: '#ffffff',
  text: '#23160c',
  muted: '#6f6156',
  border: '#eadbd0',
  accent: '#f26c1c',
  accentSoft: '#fff3e8',
  dark: '#170d08',
  darkSoft: '#4d2208',
  success: '#1f8a56',
  successSoft: '#e7f7ee',
  danger: '#b94a35',
  dangerSoft: '#fff0ec',
  bodyText: '#4d382b',
  onDark: '#fffaf5',
  onDarkMuted: '#f3dfd0',
  tabText: '#f3dfd0',
  subtleSurface: '#fff3e8',
};

const darkColors: typeof lightColors = {
  page: '#100b08',
  surface: '#18110e',
  text: '#f7ede6',
  muted: '#ccb8ab',
  border: '#33231a',
  accent: '#ff8a3d',
  accentSoft: '#26160e',
  dark: '#080605',
  darkSoft: '#231209',
  success: '#78d3a6',
  successSoft: '#143625',
  danger: '#ff9b86',
  dangerSoft: '#3a1812',
  bodyText: '#e8d6c8',
  onDark: '#fffaf5',
  onDarkMuted: '#f3dfd0',
  tabText: '#f3dfd0',
  subtleSurface: '#201510',
};

let colors = lightColors;
let styles = createStyles(colors);
const levelWaveDots = [4, 10, 16, 22, 28, 34, 40, 46, 52, 58, 64, 70, 76, 82, 88, 94];
const levelBubbles = [8, 24, 39, 57, 73, 90];

function TabButton({ label, isActive, onPress }: { label: string; isActive: boolean; onPress: () => void }) {
  return (
    <Pressable style={[styles.tabButton, isActive && styles.tabButtonActive]} onPress={onPress}>
      <Text style={[styles.tabButtonText, isActive && styles.tabButtonTextActive]}>{label}</Text>
    </Pressable>
  );
}

function StatBox({ label, value }: { label: string; value: number | string }) {
  return (
    <View style={styles.statBox}>
      <Text style={styles.statLabel} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.78}>{label}</Text>
      <Text style={styles.statValue} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.72}>{value}</Text>
    </View>
  );
}

function LevelProgressBar({ summary }: { summary: RewardSummary }) {
  const currentLevel = Math.max(0, Math.floor(Number(summary.current_level) || 0));
  const fallbackProgress = summary.next_threshold
    ? (Math.max(0, Number(summary.total_points) || 0) / Math.max(1, Number(summary.next_threshold))) * 100
    : 0;
  const rawProgress = typeof summary.progress_percent === 'number' ? summary.progress_percent : fallbackProgress;
  const progressPercent = Math.max(0, Math.min(100, Math.round(rawProgress)));

  return (
    <View style={styles.levelProgressRow}>
      <View style={styles.levelBadge}>
        <Text style={styles.levelBadgeLabel}>Lvl</Text>
        <Text style={styles.levelBadgeValue}>{currentLevel}</Text>
      </View>
      <View style={styles.levelTrack}>
        <View style={[styles.levelFill, { width: `${progressPercent}%` }]}>
          {levelWaveDots.map((position, index) => (
            <View
              key={`wave-${position}`}
              style={[
                styles.levelWaveDot,
                {
                  left: `${position}%`,
                  top: index % 4 < 2 ? 8 : 15,
                },
              ]}
            />
          ))}
          {levelBubbles.map((position, index) => (
            <View
              key={`bubble-${position}`}
              style={[
                styles.levelBubble,
                {
                  left: `${position}%`,
                  top: index % 2 === 0 ? 6 : 16,
                },
              ]}
            />
          ))}
        </View>
      </View>
    </View>
  );
}

function InfoPill({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoPill}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

function ProfileAction({ title, text }: { title: string; text: string }) {
  return (
    <View style={styles.profileAction}>
      <Text style={styles.profileActionTitle}>{title}</Text>
      <Text style={styles.profileActionText}>{text}</Text>
    </View>
  );
}

function getInitials(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return 'W2';
  }

  return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function formatDate(dateValue: string) {
  const date = new Date(dateValue.replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return dateValue;
  }

  return date.toLocaleDateString(undefined, {
    month: 'short',
    year: 'numeric',
  });
}

function formatFullDate(dateValue: string) {
  const date = new Date(`${dateValue}T12:00:00`);

  if (Number.isNaN(date.getTime())) {
    return dateValue;
  }

  return date.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

function formatDayName(dateValue: string) {
  const date = new Date(`${dateValue}T12:00:00`);
  return Number.isNaN(date.getTime()) ? 'Day' : date.toLocaleDateString(undefined, { weekday: 'short' });
}

function formatDayNumber(dateValue: string) {
  const date = new Date(`${dateValue}T12:00:00`);
  return Number.isNaN(date.getTime()) ? '--' : String(date.getDate());
}

function createStyles(colors: typeof lightColors) {
return StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.page,
  },
  appShell: {
    flex: 1,
  },
  screen: {
    flex: 1,
  },
  authScreen: {
    flex: 1,
    backgroundColor: colors.page,
    justifyContent: 'center',
  },
  authContent: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: 22,
    paddingVertical: 32,
  },
  authSkipButton: {
    position: 'absolute',
    right: 18,
    top: 16,
    zIndex: 2,
    opacity: 0.46,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  authSkipText: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '900',
  },
  authLogo: {
    width: 184,
    height: 58,
    alignSelf: 'center',
    marginBottom: 22,
  },
  authTitle: {
    color: colors.text,
    fontSize: 31,
    fontWeight: '900',
    lineHeight: 37,
    textAlign: 'center',
  },
  authSubtitle: {
    color: colors.muted,
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
    marginTop: 10,
    marginBottom: 22,
  },
  authCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 16,
    gap: 12,
  },
  authNameRow: {
    flexDirection: 'row',
    gap: 10,
  },
  authNameInput: {
    flex: 1,
  },
  authInput: {
    marginBottom: 0,
  },
  authSwitchButton: {
    alignItems: 'center',
    paddingVertical: 4,
  },
  authSwitchText: {
    color: colors.accent,
    fontSize: 14,
    fontWeight: '900',
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 114,
  },
  header: {
    paddingTop: 14,
    paddingBottom: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  brandHeaderLeft: {
    flex: 1,
    minHeight: 52,
    justifyContent: 'center',
    alignItems: 'flex-start',
  },
  logoImage: {
    width: 176,
    height: 46,
  },
  subtitle: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 4,
    maxWidth: 260,
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: 18,
    backgroundColor: colors.accentSoft,
    borderColor: colors.border,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
  },
  avatarText: {
    color: colors.accent,
    fontSize: 13,
    fontWeight: '900',
  },
  scannerScreen: {
    flex: 1,
    backgroundColor: colors.dark,
  },
  cameraPreview: {
    flex: 1,
  },
  scannerOverlay: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: 'rgba(0,0,0,0.18)',
  },
  scannerCloseButton: {
    position: 'absolute',
    top: 18,
    right: 18,
    borderRadius: 8,
    backgroundColor: 'rgba(0,0,0,0.55)',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  scannerCloseText: {
    color: colors.onDark,
    fontSize: 14,
    fontWeight: '900',
  },
  scannerFrame: {
    width: 240,
    height: 240,
    borderRadius: 8,
    borderColor: colors.accent,
    borderWidth: 4,
    backgroundColor: 'rgba(255,255,255,0.06)',
  },
  scannerTitle: {
    color: colors.onDark,
    fontSize: 25,
    fontWeight: '900',
    marginTop: 22,
  },
  scannerText: {
    color: colors.onDarkMuted,
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
    marginTop: 8,
  },
  scannerLoading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 18,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 14,
  },
  pickForMeButton: {
    backgroundColor: colors.accent,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 112,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  pickForMeText: {
    color: colors.onDark,
    fontSize: 15,
    fontWeight: '900',
  },
  pickPanel: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 14,
    marginBottom: 18,
    gap: 14,
  },
  pickPanelHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 12,
  },
  pickPanelTitle: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '900',
  },
  pickPanelSubtitle: {
    color: colors.muted,
    fontSize: 13,
    lineHeight: 19,
    marginTop: 4,
  },
  pickPanelClose: {
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  pickPanelCloseText: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '900',
  },
  pickField: {
    gap: 8,
  },
  pickFieldLabel: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  pickInput: {
    backgroundColor: colors.page,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    color: colors.text,
    fontSize: 16,
    fontWeight: '800',
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  pickChipRow: {
    gap: 8,
    paddingRight: 4,
  },
  pickChip: {
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 13,
    paddingVertical: 10,
    backgroundColor: colors.page,
  },
  pickChipSelected: {
    borderColor: colors.accent,
    backgroundColor: colors.accent,
  },
  pickChipText: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '900',
  },
  pickChipTextSelected: {
    color: colors.onDark,
  },
  pickPriceGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  pickPriceCard: {
    width: '48%',
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 13,
    paddingVertical: 12,
    backgroundColor: colors.page,
  },
  pickPriceHelper: {
    color: colors.muted,
    fontSize: 11,
    fontWeight: '800',
    marginTop: 3,
  },
  pickFindButton: {
    backgroundColor: colors.accent,
    borderRadius: 8,
    alignItems: 'center',
    paddingVertical: 14,
  },
  pickFindButtonText: {
    color: colors.onDark,
    fontSize: 15,
    fontWeight: '900',
  },
  searchInput: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    color: colors.text,
    fontSize: 16,
    paddingHorizontal: 16,
    paddingVertical: 14,
    marginBottom: 14,
  },
  discoverSearchInput: {
    flex: 1,
    marginBottom: 0,
  },
  topPicksRow: {
    gap: 12,
    paddingBottom: 20,
  },
  topPickCard: {
    width: 172,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    overflow: 'hidden',
  },
  topPickImage: {
    width: '100%',
    height: 106,
    backgroundColor: colors.accentSoft,
  },
  topPickBody: {
    padding: 12,
    minHeight: 94,
  },
  topPickKicker: {
    color: colors.accent,
    fontSize: 11,
    fontWeight: '900',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  topPickName: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '900',
    lineHeight: 21,
  },
  topPickArea: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
    marginTop: 6,
  },
  catalogShelf: {
    marginBottom: 4,
  },
  categoryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 16,
  },
  categoryCard: {
    width: '48%',
    minHeight: 90,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 14,
    justifyContent: 'space-between',
  },
  categoryCardSelected: {
    backgroundColor: colors.accent,
    borderColor: colors.accent,
  },
  categoryCardTitle: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '900',
  },
  categoryCardTitleSelected: {
    color: '#ffffff',
  },
  categoryCardCount: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
    marginTop: 10,
  },
  categoryCardCountSelected: {
    color: '#ffffff',
  },
  categoryRow: {
    gap: 10,
    paddingBottom: 16,
  },
  categoryPill: {
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 15,
    paddingVertical: 9,
    backgroundColor: colors.surface,
  },
  categoryPillSelected: {
    backgroundColor: colors.accent,
    borderColor: colors.accent,
  },
  categoryText: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '800',
  },
  categoryTextSelected: {
    color: '#ffffff',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 12,
  },
  sectionTitle: {
    color: colors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  sectionCount: {
    color: colors.muted,
    fontSize: 13,
    fontWeight: '800',
  },
  pageHeader: {
    paddingTop: 24,
    paddingBottom: 20,
  },
  pageTitle: {
    color: colors.text,
    fontSize: 30,
    fontWeight: '900',
  },
  pageSubtitle: {
    color: colors.muted,
    fontSize: 15,
    lineHeight: 22,
    marginTop: 8,
  },
  placeCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    marginBottom: 16,
    overflow: 'hidden',
  },
  placeImage: {
    width: '100%',
    height: 180,
    backgroundColor: colors.accentSoft,
  },
  imageFallback: {
    backgroundColor: colors.accentSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageFallbackText: {
    color: colors.accent,
    fontSize: 32,
    fontWeight: '900',
  },
  placeBody: {
    padding: 16,
  },
  placeMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 8,
  },
  placeCategory: {
    color: colors.accent,
    flex: 1,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  placeRating: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
  },
  placeName: {
    color: colors.text,
    fontSize: 20,
    fontWeight: '900',
    lineHeight: 26,
  },
  placeArea: {
    color: colors.muted,
    fontSize: 14,
    marginTop: 4,
  },
  placeDescription: {
    color: colors.bodyText,
    fontSize: 14,
    lineHeight: 21,
    marginTop: 10,
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 14,
  },
  badgeText: {
    color: colors.success,
    fontSize: 13,
    fontWeight: '900',
  },
  actionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    marginTop: 16,
  },
  primaryButton: {
    backgroundColor: colors.accent,
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: colors.onDark,
    fontSize: 14,
    fontWeight: '900',
  },
  secondaryButton: {
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 11,
    backgroundColor: colors.surface,
  },
  secondaryButtonActive: {
    backgroundColor: colors.accentSoft,
    borderColor: '#f6b27d',
  },
  secondaryButtonText: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  secondaryButtonTextActive: {
    color: colors.accent,
  },
  dangerButton: {
    backgroundColor: colors.dangerSoft,
    borderColor: colors.danger,
  },
  dangerButtonText: {
    color: colors.danger,
  },
  disabledButton: {
    backgroundColor: '#c8b8aa',
  },
  priceText: {
    color: colors.accent,
    fontSize: 15,
    fontWeight: '900',
  },
  emptyState: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 22,
    gap: 14,
  },
  emptyTitle: {
    color: colors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  emptyText: {
    color: colors.muted,
    fontSize: 15,
    lineHeight: 22,
  },
  bookingCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 16,
    marginBottom: 14,
  },
  bookingCancelButton: {
    alignSelf: 'flex-start',
    marginTop: 14,
  },
  levelProgressRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: -2,
    marginBottom: 16,
  },
  levelBadge: {
    width: 46,
    height: 34,
    borderRadius: 8,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  levelBadgeLabel: {
    color: colors.muted,
    fontSize: 9,
    fontWeight: '900',
    lineHeight: 11,
    textTransform: 'uppercase',
  },
  levelBadgeValue: {
    color: colors.accent,
    fontSize: 16,
    fontWeight: '900',
    lineHeight: 18,
  },
  levelTrack: {
    flex: 1,
    height: 28,
    borderRadius: 8,
    borderColor: colors.border,
    borderWidth: 1,
    backgroundColor: colors.surface,
    overflow: 'hidden',
  },
  levelFill: {
    height: '100%',
    minWidth: 0,
    backgroundColor: colors.accent,
    overflow: 'hidden',
    position: 'relative',
  },
  levelWaveDot: {
    position: 'absolute',
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: 'rgba(255, 250, 245, 0.26)',
  },
  levelBubble: {
    position: 'absolute',
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: 'rgba(255, 250, 245, 0.55)',
  },
  profileCard: {
    backgroundColor: colors.dark,
    borderRadius: 8,
    flexDirection: 'row',
    gap: 14,
    padding: 18,
    marginBottom: 16,
  },
  profileAvatar: {
    width: 64,
    height: 64,
    borderRadius: 22,
    backgroundColor: colors.accentSoft,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  profileAvatarImage: {
    width: '100%',
    height: '100%',
  },
  profileAvatarText: {
    color: colors.accent,
    fontSize: 20,
    fontWeight: '900',
  },
  profileInfo: {
    flex: 1,
  },
  profileName: {
    color: '#ffffff',
    fontSize: 22,
    fontWeight: '900',
  },
  profileEmail: {
    color: colors.onDarkMuted,
    fontSize: 14,
    marginTop: 5,
  },
  profileNote: {
    color: '#ffb17b',
    fontSize: 13,
    marginTop: 8,
  },
  profileNotice: {
    marginBottom: 14,
  },
  profileQuickActions: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 16,
  },
  profileQuickButton: {
    flex: 1,
  },
  statsGrid: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 16,
  },
  statBox: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    flex: 1,
    minWidth: 0,
    minHeight: 74,
    justifyContent: 'center',
    paddingHorizontal: 6,
    paddingVertical: 10,
  },
  statValue: {
    color: colors.accent,
    fontSize: 22,
    fontWeight: '900',
    textAlign: 'center',
  },
  statLabel: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
    marginTop: 4,
    textAlign: 'center',
  },
  profileSection: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 16,
    gap: 12,
    marginBottom: 16,
  },
  profileSectionTitle: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '900',
  },
  rewardMetricGrid: {
    flexDirection: 'row',
    gap: 6,
  },
  rewardList: {
    borderTopColor: colors.border,
    borderTopWidth: 1,
    paddingTop: 12,
    gap: 4,
  },
  profileAction: {
    borderTopColor: colors.border,
    borderTopWidth: 1,
    paddingTop: 12,
  },
  profileActionTitle: {
    color: colors.text,
    fontSize: 15,
    fontWeight: '900',
  },
  profileActionText: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 4,
  },
  settingsCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 16,
    gap: 16,
    marginBottom: 14,
  },
  settingsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  settingsIcon: {
    width: 46,
    height: 46,
    borderRadius: 16,
    backgroundColor: colors.accentSoft,
    borderColor: colors.border,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  settingsIconText: {
    color: colors.accent,
    fontSize: 16,
    fontWeight: '900',
  },
  settingsCopy: {
    flex: 1,
  },
  settingsTitle: {
    color: colors.text,
    fontSize: 17,
    fontWeight: '900',
  },
  settingsText: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 4,
  },
  segmentedControl: {
    flexDirection: 'row',
    gap: 8,
    backgroundColor: colors.accentSoft,
    borderRadius: 8,
    padding: 6,
  },
  segmentButton: {
    flex: 1,
    borderRadius: 8,
    paddingVertical: 11,
    alignItems: 'center',
  },
  segmentButtonActive: {
    backgroundColor: colors.accent,
  },
  segmentButtonDisabled: {
    opacity: 0.5,
  },
  segmentText: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '900',
  },
  segmentTextActive: {
    color: '#ffffff',
  },
  switchTrack: {
    width: 58,
    height: 34,
    borderRadius: 999,
    padding: 4,
    backgroundColor: colors.accentSoft,
    borderColor: colors.border,
    borderWidth: 1,
    justifyContent: 'center',
  },
  switchTrackActive: {
    backgroundColor: colors.accent,
    borderColor: colors.accent,
  },
  switchThumb: {
    width: 24,
    height: 24,
    borderRadius: 999,
    backgroundColor: colors.surface,
  },
  switchThumbActive: {
    transform: [{ translateX: 24 }],
  },
  backButton: {
    alignSelf: 'flex-start',
    marginTop: 18,
    marginBottom: 14,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
  },
  backButtonText: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  detailCard: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    overflow: 'hidden',
  },
  detailCardThemed: {
    borderWidth: 2,
  },
  detailImage: {
    width: '100%',
    height: 250,
    backgroundColor: colors.accentSoft,
  },
  detailBody: {
    padding: 18,
  },
  detailTitle: {
    color: colors.text,
    fontSize: 28,
    fontWeight: '900',
    lineHeight: 34,
  },
  businessThemeTagline: {
    fontSize: 15,
    fontWeight: '900',
    lineHeight: 22,
    marginTop: 8,
  },
  businessThemeBadge: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: 8,
    marginTop: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  businessThemeBadgeText: {
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  detailDescription: {
    color: colors.bodyText,
    fontSize: 15,
    lineHeight: 23,
    marginTop: 14,
  },
  infoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 18,
  },
  infoPill: {
    backgroundColor: colors.accentSoft,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    minWidth: '47%',
    padding: 12,
  },
  infoLabel: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
  },
  infoValue: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '900',
    marginTop: 4,
  },
  detailSection: {
    marginTop: 18,
  },
  detailSectionTitle: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '900',
  },
  detailSectionText: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 21,
    marginTop: 6,
  },
  promoBox: {
    backgroundColor: colors.accentSoft,
    borderColor: '#f6d1b0',
    borderWidth: 1,
    borderRadius: 8,
    padding: 14,
    marginTop: 18,
  },
  promoTitle: {
    color: colors.accent,
    fontSize: 15,
    fontWeight: '900',
  },
  promoText: {
    color: colors.bodyText,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 5,
  },
  reservationPanel: {
    borderTopColor: colors.border,
    borderTopWidth: 1,
    marginTop: 20,
    paddingTop: 18,
    gap: 14,
  },
  guestRow: {
    backgroundColor: colors.accentSoft,
    borderColor: colors.border,
    borderWidth: 1,
    borderRadius: 8,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  guestLabel: {
    color: colors.text,
    fontSize: 15,
    fontWeight: '900',
  },
  stepper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  stepButton: {
    width: 34,
    height: 34,
    borderRadius: 8,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepText: {
    color: colors.accent,
    fontSize: 20,
    fontWeight: '900',
  },
  guestCount: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '900',
    minWidth: 24,
    textAlign: 'center',
  },
  dayRow: {
    gap: 10,
  },
  dayCard: {
    width: 78,
    borderRadius: 8,
    borderColor: colors.border,
    borderWidth: 1,
    backgroundColor: colors.surface,
    padding: 10,
    alignItems: 'center',
    gap: 2,
  },
  dayCardSelected: {
    backgroundColor: colors.accent,
    borderColor: colors.accent,
  },
  dayCardDisabled: {
    opacity: 0.45,
  },
  dayName: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
  },
  dayNumber: {
    color: colors.text,
    fontSize: 22,
    fontWeight: '900',
  },
  dayStatus: {
    color: colors.muted,
    fontSize: 11,
    fontWeight: '800',
  },
  dayTextSelected: {
    color: '#ffffff',
  },
  slotGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  slotPill: {
    borderRadius: 8,
    borderColor: colors.border,
    borderWidth: 1,
    backgroundColor: colors.surface,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  slotPillSelected: {
    backgroundColor: colors.dark,
    borderColor: colors.dark,
  },
  slotPillDisabled: {
    opacity: 0.36,
  },
  slotText: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '900',
  },
  slotTextSelected: {
    color: '#ffffff',
  },
  reservationNotice: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.accentSoft,
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  reservationNoticeSuccess: {
    borderColor: colors.success,
    backgroundColor: colors.successSoft,
  },
  reservationNoticeError: {
    borderColor: colors.danger,
    backgroundColor: colors.dangerSoft,
  },
  reservationNoticeText: {
    color: colors.accent,
    fontSize: 14,
    lineHeight: 20,
    fontWeight: '800',
  },
  reservationNoticeTextSuccess: {
    color: colors.success,
  },
  reservationNoticeTextError: {
    color: colors.danger,
  },
  detailActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 20,
  },
  tabBar: {
    position: 'absolute',
    left: 14,
    right: 14,
    bottom: 14,
    backgroundColor: colors.dark,
    borderRadius: 8,
    flexDirection: 'row',
    gap: 4,
    padding: 6,
  },
  tabButton: {
    flex: 1,
    borderRadius: 8,
    paddingVertical: 11,
    alignItems: 'center',
  },
  tabButtonActive: {
    backgroundColor: colors.accent,
  },
  tabButtonText: {
    color: colors.tabText,
    fontSize: 12,
    fontWeight: '900',
  },
  tabButtonTextActive: {
    color: '#ffffff',
  },
});
}
