import { XMLBuilder } from 'fast-xml-parser';

/**
 * Convert data object to XML string
 */
export function jsonToXml(data: Record<string, unknown>): string {
  const builder = new XMLBuilder({
    format: true,
    ignoreAttributes: false,
    processEntities: true,
    suppressEmptyNode: false,
  });

  const xmlContent = builder.build({ inbound: data });
  return '<?xml version="1.0" encoding="UTF-8"?>\n' + xmlContent;
}

/**
 * Flatten nested object for CSV conversion
 */
export function flattenObject(
  obj: Record<string, unknown>,
  prefix = ''
): Record<string, string> {
  const result: Record<string, string> = {};

  for (const [key, value] of Object.entries(obj)) {
    const newKey = prefix ? `${prefix}.${key}` : key;

    if (value && typeof value === 'object' && !Array.isArray(value)) {
      Object.assign(result, flattenObject(value as Record<string, unknown>, newKey));
    } else if (Array.isArray(value)) {
      value.forEach((item, index) => {
        if (typeof item === 'object' && item !== null) {
          Object.assign(
            result,
            flattenObject(item as Record<string, unknown>, `${newKey}.${index}`)
          );
        } else {
          result[`${newKey}.${index}`] = String(item ?? '');
        }
      });
    } else {
      result[newKey] = String(value ?? '');
    }
  }

  return result;
}

/**
 * Convert flattened object to CSV string
 */
export function objectToCsv(data: Record<string, string>): string {
  const keys = Object.keys(data);
  const values = Object.values(data);

  const escapeCsv = (val: string) => {
    if (val.includes(',') || val.includes('"') || val.includes('\n')) {
      return `"${val.replace(/"/g, '""')}"`;
    }
    return val;
  };

  const header = keys.map(escapeCsv).join(',');
  const row = values.map(escapeCsv).join(',');

  return header + '\n' + row + '\n';
}

/**
 * Format date to Jakarta timezone
 */
export function formatDate(format: string = 'datetime'): string {
  const now = new Date();
  const jakartaTime = new Date(
    now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })
  );

  const pad = (n: number) => n.toString().padStart(2, '0');

  const y = jakartaTime.getFullYear();
  const m = pad(jakartaTime.getMonth() + 1);
  const d = pad(jakartaTime.getDate());
  const h = pad(jakartaTime.getHours());
  const min = pad(jakartaTime.getMinutes());
  const s = pad(jakartaTime.getSeconds());

  if (format === 'file') {
    return `${y}-${m}-${d}_${h}-${min}-${s}`;
  }

  return `${y}-${m}-${d} ${h}:${min}:${s}`;
}

/**
 * Generate unique ID (similar to PHP uniqid)
 */
export function uniqueId(): string {
  return Date.now().toString(16) + Math.random().toString(16).slice(2, 8);
}

/**
 * Parse ISO date string to PostgreSQL timestamp format
 */
export function parseTimestamp(dateStr?: string): string | null {
  if (!dateStr) return null;
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return null;
    return d.toISOString().replace('T', ' ').replace('Z', '');
  } catch {
    return null;
  }
}
