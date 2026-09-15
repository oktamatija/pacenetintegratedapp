/**
 * Universal Bilingual Duration Parser (Indonesian & English)
 * Translates human inputs ('12 jam', '12 hours', '12h', '1 hari', '1d', '30 menit', '30m', '1 minggu', '1w')
 * into valid MikroTik format ('12h', '1d', '30m', '7d') and human readable labels.
 */
export function parseBilingualDuration(str) {
  if (!str || typeof str !== 'string' || !str.trim()) return null;
  let s = str.trim().toLowerCase();

  if (s === '0' || s === 'none' || s === '-') return null;

  let days = 0;
  let hours = 0;
  let minutes = 0;
  let matched = false;

  // HH:MM:SS or HH:MM
  const timeMatch = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
  if (timeMatch) {
    hours = parseInt(timeMatch[1], 10);
    minutes = parseInt(timeMatch[2], 10);
    matched = true;
  } else {
    // Months: bulan / bln / month(s) / mo -> convert to 30 days
    const moMatch = s.match(/(\d+)\s*(?:bulan|bln|months?|mo)(?![a-z])/i);
    if (moMatch) {
      days += parseInt(moMatch[1], 10) * 30;
      matched = true;
      s = s.replace(/(\d+)\s*(?:bulan|bln|months?|mo)(?![a-z])/i, ' ');
    }

    // Weeks: minggu / mgg / week(s) / w
    const wkMatch = s.match(/(\d+)\s*(?:minggu|mgg|weeks?|w)(?![a-z])/i);
    if (wkMatch) {
      days += parseInt(wkMatch[1], 10) * 7;
      matched = true;
      s = s.replace(/(\d+)\s*(?:minggu|mgg|weeks?|w)(?![a-z])/i, ' ');
    }

    // Days: hari / hr / day(s) / d
    const dMatch = s.match(/(\d+)\s*(?:hari|days?|d)(?![a-z])/i);
    if (dMatch) {
      days += parseInt(dMatch[1], 10);
      matched = true;
      s = s.replace(/(\d+)\s*(?:hari|days?|d)(?![a-z])/i, ' ');
    }

    // Hours: jam / jm / j / hour(s) / hrs? / h
    const hMatch = s.match(/(\d+)\s*(?:jam|jm|hours?|hrs?|h|j)(?![a-z])/i);
    if (hMatch) {
      hours += parseInt(hMatch[1], 10);
      matched = true;
      s = s.replace(/(\d+)\s*(?:jam|jm|hours?|hrs?|h|j)(?![a-z])/i, ' ');
    }

    // Minutes: menit / mnt / minute(s) / min(s) / m
    const mMatch = s.match(/(\d+)\s*(?:menit|mnt|minutes?|mins?|m)(?![a-z])/i);
    if (mMatch) {
      minutes += parseInt(mMatch[1], 10);
      matched = true;
      s = s.replace(/(\d+)\s*(?:menit|mnt|minutes?|mins?|m)(?![a-z])/i, ' ');
    }

    // Raw number without unit -> default to hours (e.g. "12" -> 12 hours)
    if (!matched && /^\d+$/.test(s.trim())) {
      hours = parseInt(s.trim(), 10);
      matched = true;
    }
  }

  if (!matched || (days === 0 && hours === 0 && minutes === 0)) return null;

  const mtParts = [];
  if (days > 0) mtParts.push(`${days}d`);
  if (hours > 0) mtParts.push(`${hours}h`);
  if (minutes > 0) mtParts.push(`${minutes}m`);
  const mtStr = mtParts.join('');

  const idParts = [];
  if (days > 0) {
    if (days % 30 === 0) idParts.push(`${days / 30} Bulan`);
    else if (days % 7 === 0) idParts.push(`${days / 7} Minggu`);
    else idParts.push(`${days} Hari`);
  }
  if (hours > 0) idParts.push(`${hours} Jam`);
  if (minutes > 0) idParts.push(`${minutes} Menit`);

  return {
    valid: true,
    mikrotik: mtStr,
    seconds: (days * 86400) + (hours * 3600) + (minutes * 60),
    humanId: idParts.join(' ')
  };
}
