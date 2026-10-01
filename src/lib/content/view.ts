import { pairingTypesForRide, pairingsForRide, parkForRide, type ContentGraph } from './graph';
import type {
	FurtherReading,
	Pairing,
	PairingType,
	Park,
	Ride,
	RideStats,
	RideType
} from './types';

/**
 * View models: the plain, serialisable shapes that load functions hand to pages.
 *
 * Because every page is prerendered, whatever a load function returns is baked
 * into a JSON payload. Building narrow summaries here keeps each page's payload
 * to what it actually renders, rather than shipping the whole content graph and
 * every word of every write-up.
 */

export interface ParkRef {
	id: string;
	name: string;
	country: string;
	region: string;
}

/** A ride as it appears in a list or card. No body text. */
export interface RideSummary {
	id: string;
	name: string;
	rideType: RideType;
	opened: number;
	/** `YYYY-MM-DD`, when given. */
	added?: string;
	intensity: number;
	roughness: number;
	vibe: string[];
	gist: string;
	park: ParkRef;
	/** Distinct pairing types this ride covers, in moment order. Drives the filters. */
	pairingTypes: PairingType[];
	pairingCount: number;
	featured: boolean;
	draft: boolean;
}

/** A pairing as it appears in a list or card. No body text. */
export interface PairingSummary {
	id: string;
	title: string;
	pairingType: PairingType;
	description: string;
	url?: string;
	image?: string;
	rideCount: number;
	draft: boolean;
}

/** A pairing on a ride page: the pairing, plus this ride's take on it. */
export interface RidePairingView {
	pairing: PairingSummary;
	blurb: string;
	strength: number;
}

/** A ride on a pairing page: the reverse of {@link RidePairingView}. */
export interface PairedRideView {
	ride: RideSummary;
	blurb: string;
	strength: number;
}

export function toParkRef(graph: ContentGraph, ride: Ride): ParkRef {
	const park = parkForRide(graph, ride);
	return { id: park.id, name: park.name, country: park.country, region: park.region };
}

export function toRideSummary(graph: ContentGraph, ride: Ride): RideSummary {
	return {
		id: ride.id,
		name: ride.name,
		rideType: ride.rideType,
		opened: ride.opened,
		added: ride.added,
		intensity: ride.intensity,
		roughness: ride.roughness,
		vibe: ride.vibe,
		gist: ride.gist,
		park: toParkRef(graph, ride),
		pairingTypes: pairingTypesForRide(graph, ride),
		pairingCount: ride.pairings.length,
		featured: ride.featured,
		draft: ride.draft
	};
}

export function toPairingSummary(graph: ContentGraph, pairing: Pairing): PairingSummary {
	return {
		id: pairing.id,
		title: pairing.title,
		pairingType: pairing.pairingType,
		description: pairing.description,
		url: pairing.url,
		image: pairing.image,
		rideCount: graph.ridesByPairing.get(pairing.id)?.length ?? 0,
		draft: pairing.draft
	};
}

export interface ParkSummary extends ParkRef {
	website?: string;
	rideCount: number;
	draft: boolean;
}

export function toParkSummary(graph: ContentGraph, park: Park): ParkSummary {
	return {
		id: park.id,
		name: park.name,
		country: park.country,
		region: park.region,
		website: park.website,
		rideCount: graph.ridesByPark.get(park.id)?.length ?? 0,
		draft: park.draft
	};
}

export interface PairingDetail extends PairingSummary {
	body: string;
}

export function toPairingDetail(graph: ContentGraph, pairing: Pairing): PairingDetail {
	return { ...toPairingSummary(graph, pairing), body: pairing.body };
}

export interface ParkDetail extends ParkSummary {
	body: string;
}

export function toParkDetail(graph: ContentGraph, park: Park): ParkDetail {
	return { ...toParkSummary(graph, park), body: park.body };
}

export function toRidePairingViews(graph: ContentGraph, ride: Ride): RidePairingView[] {
	return pairingsForRide(graph, ride).map(({ pairing, blurb, strength }) => ({
		pairing: toPairingSummary(graph, pairing),
		blurb,
		strength
	}));
}

/** The full ride page: everything the template needs, and nothing more. */
export interface RideDetail extends RideSummary {
	manufacturer: string;
	stats?: RideStats;
	body: string;
	pairings: RidePairingView[];
	furtherReading: FurtherReading[];
}

export function toRideDetail(graph: ContentGraph, ride: Ride): RideDetail {
	return {
		...toRideSummary(graph, ride),
		manufacturer: ride.manufacturer,
		stats: ride.stats,
		body: ride.body,
		pairings: toRidePairingViews(graph, ride),
		furtherReading: ride.furtherReading
	};
}
