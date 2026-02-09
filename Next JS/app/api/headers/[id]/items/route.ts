/**
 * @file    GET /api/headers/:id/items — Line items for a header
 * @module  api/headers/[id]/items
 * @author  Wahyu Amaldi — Technical Lead, KPMG
 * @version 1.0.0
 */

import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

/**
 * GET /api/headers/[id]/items - Get items for a header
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const headerId = parseInt(id);

  if (isNaN(headerId)) {
    return NextResponse.json(
      { success: false, message: 'Invalid header ID' },
      { status: 400 }
    );
  }

  try {
    const result = await query(
      'SELECT * FROM inbound_items WHERE header_id = $1 ORDER BY line_num ASC',
      [headerId]
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
