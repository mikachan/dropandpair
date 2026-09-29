import { PAIRING_TYPES } from './schemas';
import type { PairingType, RideType } from './types';

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
	aperitif: 'Aperitif',
	chaser: 'Chaser',
	'palate-cleanser': 'Palate cleanser',
	'eat-and-drink': 'Eat and drink',
	'do-next': 'Do next',
	listen: 'Listen',
	read: 'Read',
	watch: 'Watch',
	nightcap: 'Nightcap'
};

/** The one-line explanation of each course, used on browse pages. */
export const pairingTypeDescriptions: Record<PairingType, string> = {
	aperitif: 'Something to do before you ride.',
	chaser: 'Take this one straight after.',
	'palate-cleanser': 'Something gentle, for after a brutal ride.',
	'eat-and-drink': 'What to put in your hands once the restraints are up.',
	'do-next': 'Where to walk while the adrenaline wears off.',
	listen: 'For the queue, the car park, or the long drive home.',
	read: 'For the hotel, the flight, or the winter closed season.',
	watch: 'A film or a show that belongs with this ride.',
	nightcap: 'Save this one for later.'
};

/** Sort key giving the order of a tasting flight: before, after, then later. */
export function pairingTypeRank(type: PairingType): number {
	return PAIRING_TYPES.indexOf(type);
}

/** Intensity, as body. */
export const bodyLabels: Record<number, string> = {
	1: 'Light-bodied',
	2: 'Easy-going',
	3: 'Medium-bodied',
	4: 'Full-bodied',
	5: 'Cask strength'
};

/** Roughness, as finish — how you feel walking off. */
export const finishLabels: Record<number, string> = {
	1: 'Silky finish',
	2: 'Smooth finish',
	3: 'Textured finish',
	4: 'Coarse finish',
	5: 'Abrasive finish'
};

/** How well a pairing suits its ride. */
export const strengthLabels: Record<number, string> = {
	1: 'Worth a try',
	2: 'Gentle match',
	3: 'Good match',
	4: 'Strong match',
	5: 'Perfect marriage'
};

export function bodyLabel(intensity: number): string {
	return bodyLabels[intensity] ?? 'Unrated body';
}

export function finishLabel(roughness: number): string {
	return finishLabels[roughness] ?? 'Unrated finish';
}

export function strengthLabel(strength: number): string {
	return strengthLabels[strength] ?? 'Unrated';
}

/** Opening year, as vintage. */
export function vintageLabel(opened: number): string {
	return `Vintage ${opened}`;
}

export const statLabels = {
	height: { label: 'Height', unit: 'm' },
	speed: { label: 'Top speed', unit: 'km/h' },
	length: { label: 'Length', unit: 'm' },
	inversions: { label: 'Inversions', unit: '' }
} as const;
