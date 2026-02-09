import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

/**
 * GET /api/headers/[id] - Get header detail
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
    const headerResult = await query('SELECT * FROM inbound_headers WHERE id = $1', [headerId]);

    if (headerResult.rows.length === 0) {
      return NextResponse.json(
        { success: false, message: 'Header not found' },
        { status: 404 }
      );
    }

    const itemsResult = await query(
      'SELECT * FROM inbound_items WHERE header_id = $1 ORDER BY line_num ASC',
      [headerId]
    );

    const approvalsResult = await query(
      'SELECT * FROM inbound_approvals WHERE header_id = $1 ORDER BY position ASC',
      [headerId]
    );

    const rfcResult = await query(
      'SELECT * FROM rfc_call_history WHERE header_id = $1 ORDER BY call_timestamp DESC',
      [headerId]
    );

    return NextResponse.json({
      success: true,
      data: {
        header: headerResult.rows[0],
        items: itemsResult.rows,
        approvals: approvalsResult.rows,
        rfc_history: rfcResult.rows,
      },
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
