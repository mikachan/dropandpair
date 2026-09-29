/** Escapes text for use inside an XML element or attribute. */
export function escapeXml(value: string): string {
	return value
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&apos;');
}

/** `2026-09-29` -> `Tue, 29 Sep 2026 00:00:00 GMT`, the format RSS wants. */
export function toRfc822(isoDate: string): string {
	return new Date(`${isoDate}T00:00:00Z`).toUTCString();
}
