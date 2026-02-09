/**
 * @file    GET /api/headers — JSON REST API for header listing
 * @module  api/headers
 * @author  Wahyu Amaldi — Technical Lead, KPMG
 * @version 1.0.0
 */

import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

/**
 * GET /api/headers - List all inbound headers (JSON API)
 */
export async function GET() {
  try {
    const result = await query(
      `SELECT h.*, 
        (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) as item_count,
        (SELECT COUNT(*) FROM inbound_approvals WHERE header_id = h.id) as approval_count
      FROM inbound_headers h 
      WHERE (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) > 0
      ORDER BY h.received_at DESC`
    );

    return NextResponse.json({
      success: true,
      count: result.rows.length,
      data: result.rows,
    });
  } catch (err) {
    return NextResponse.json(
      {
        success: false,
        message: err instanceof Error ? err.message : 'Database error',
      },
      { status: 500 }
    );
  }
}
