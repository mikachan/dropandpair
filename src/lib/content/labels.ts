import { PAIRING_TYPES, type PairingType, type RideType } from './constants';

/**
 * Every bit of UI vocabulary lives here, so the house style is consistent and
 * changing a label is a one-line edit.
 */

export const rideTypeLabels: Record<RideType, string> = {
	coaster: 'Coaster',
	'dark-ride': 'Dark ride',
	'flat-ride': 'Flat ride',
	'water-ride': 'Water ride',
	'drop-tower': 'Drop tower'
};

export const pairingTypeLabels: Record<PairingType, string> = {
	before: 'Before',
	'right-after': 'Right after',
	'wind-down': 'Wind down',
	'eat-and-drink': 'Eat and drink',
	'do-next': 'Do next',
	listen: 'Listen',
	read: 'Read',
	watch: 'Watch',
	later: 'Later'
};

/** The one-line explanation of each course, used on browse pages. */
export const pairingTypeDescriptions: Record<PairingType, string> = {
	before: 'Something to do before you ride.',
	'right-after': 'Take this one straight after.',
	'wind-down': 'Something gentle, for after a brutal ride.',
	'eat-and-drink': 'What to put in your hands once the restraints are up.',
	'do-next': 'Where to walk while the adrenaline wears off.',
	listen: 'For the queue, the car park, or the long drive home.',
	read: 'For the hotel, the flight, or the winter closed season.',
	watch: 'A film or a show that belongs with this ride.',
	later: 'Save this one for later.'
};

/** Sort key giving the order you would meet them in: before, after, then later. */
export function pairingTypeRank(type: PairingType): number {
	return PAIRING_TYPES.indexOf(type);
}

/** Intensity — how hard it hits. */
export const intensityLabels: Record<number, string> = {
	1: 'Gentle',
	2: 'Mild',
	3: 'Moderate',
	4: 'Strong',
	5: 'Maximum'
};

/** Roughness, as comfort — how you feel walking off. */
export const comfortLabels: Record<number, string> = {
	1: 'Silky',
	2: 'Smooth',
	3: 'Bumpy',
	4: 'Rough',
	5: 'Brutal'
};

/** How well a pairing suits its ride. */
export const strengthLabels: Record<number, string> = {
	1: 'Worth a try',
	2: 'Gentle match',
	3: 'Good match',
	4: 'Strong match',
	5: 'Perfect marriage'
};

export function intensityLabel(intensity: number): string {
	return intensityLabels[intensity] ?? 'Unrated intensity';
}

export function comfortLabel(roughness: number): string {
	return comfortLabels[roughness] ?? 'Unrated comfort';
}

export function strengthLabel(strength: number): string {
	return strengthLabels[strength] ?? 'Unrated';
}

export const statLabels = {
	height: { label: 'Height', unit: 'm' },
	speed: { label: 'Top speed', unit: 'km/h' },
	length: { label: 'Length', unit: 'm' },
	inversions: { label: 'Inversions', unit: '' }
} as const;
