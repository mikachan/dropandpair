import type { z } from 'zod';
import type {
	PAIRING_TYPES,
	RIDE_TYPES,
	pairingSchema,
	parkSchema,
	rideSchema,
	rideStatsSchema
} from './schemas';

export type RideType = (typeof RIDE_TYPES)[number];
export type PairingType = (typeof PAIRING_TYPES)[number];

/** Added to every entry by the parser, so errors and URLs can point back at the file. */
export interface ContentMeta {
	/** Rendered Markdown body. Empty string when the file has no body. */
	body: string;
	/** Path of the source file, relative to the repo root. */
	sourcePath: string;
}

export type RideStats = z.output<typeof rideStatsSchema>;
export type Ride = z.output<typeof rideSchema> & ContentMeta;
export type Park = z.output<typeof parkSchema> & ContentMeta;
export type Pairing = z.output<typeof pairingSchema> & ContentMeta;

/** A pairing as it appears on one ride: the pairing plus that ride's take on it. */
export interface RidePairing {
	pairing: Pairing;
	blurb: string;
	strength: number;
}

/** A ride as it appears on one pairing's page: the reverse of {@link RidePairing}. */
export interface PairedRide {
	ride: Ride;
	blurb: string;
	strength: number;
}
