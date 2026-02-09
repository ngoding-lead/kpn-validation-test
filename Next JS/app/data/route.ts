import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { readFileSync, existsSync } from 'fs';
import path from 'path';

const INBOUND_DIR = path.join(process.cwd(), 'inbound');

/**
 * GET /data - Show HTML table or return file
 */
export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);

  // --- Return raw file ---
  const file = searchParams.get('file');
  if (file) {
    const filename = path.basename(file);
    const filepath = path.join(INBOUND_DIR, filename);

    if (!existsSync(filepath) || filename === '.gitkeep') {
      return NextResponse.json(
        { success: false, message: 'File not found' },
        { status: 404 }
      );
    }

    const ext = path.extname(filename).slice(1);
    const contentTypes: Record<string, string> = {
      json: 'application/json',
      xml: 'application/xml',
      csv: 'text/csv',
    };

    const content = readFileSync(filepath, 'utf-8');
    return new NextResponse(content, {
      headers: {
        'Content-Type': contentTypes[ext] || 'text/plain',
        'Content-Disposition': `inline; filename="${filename}"`,
      },
    });
  }

  // --- Items view ---
  const itemsParam = searchParams.get('items');
  if (itemsParam) {
    const headerId = parseInt(itemsParam);
    return renderItemsPage(headerId);
  }

  // --- Approvals view ---
  const approvalsParam = searchParams.get('approvals');
  if (approvalsParam) {
    const headerId = parseInt(approvalsParam);
    return renderApprovalsPage(headerId);
  }

  // --- Main data table ---
  return renderMainPage();
}

async function renderMainPage() {
  let headers = [];
  let dbError = null;

  try {
    const result = await query(
      `SELECT h.*, 
        (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) as item_count,
        (SELECT COUNT(*) FROM inbound_approvals WHERE header_id = h.id) as approval_count
      FROM inbound_headers h 
      WHERE (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) > 0
      ORDER BY h.received_at DESC`
    );
    headers = result.rows;
  } catch (err) {
    dbError = err instanceof Error ? err.message : 'Database error';
  }

  const rowsHtml = headers.length === 0
    ? `<tr><td colspan="11" class="empty">No data found. Send POST requests to add data.</td></tr>`
    : headers.map((row: Record<string, string | number>) => {
        const statusClass = String(row.status ?? '').toLowerCase().replace(/ /g, '_');
        const total = parseFloat(String(row.total ?? '0')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return `<tr>
          <td>${e(row.id)}</td>
          <td>${e(row.received_at)}</td>
          <td>${e(row.requisition_id ?? '-')}</td>
          <td><span class="status ${statusClass}">${e(row.status ?? '-')}</span></td>
          <td>${e(row.requested_by_login ?? '-')}</td>
          <td class="truncate" title="${e(row.ship_to_address_name ?? '')}">${e(row.ship_to_address_name ?? '-')}</td>
          <td class="number">${total}</td>
          <td>${e(row.currency_code ?? '-')}</td>
          <td class="number">${e(row.item_count)}</td>
          <td class="number">${e(row.approval_count ?? 0)}</td>
          <td class="actions">
            <a href="/data?items=${row.id}" class="view-items">📋 Items</a>
            <a href="/data?approvals=${row.id}" class="view-approvals">🔗 Approvals</a>
            <a href="/data?file=${encodeURIComponent(String(row.json_filename))}" class="view-json" target="_blank">JSON</a>
          </td>
        </tr>`;
      }).join('\n');

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KPN Validation - Inbound Data (Next.js)</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1600px; margin: 0 auto; }
    h1 { color: #333; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 20px; }
    .badge { display: inline-block; background: #0070f3; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px; vertical-align: middle; }
    .stats { display: flex; gap: 20px; margin-bottom: 20px; }
    .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .stat-card h3 { margin: 0 0 5px 0; color: #666; font-size: 14px; }
    .stat-card .value { font-size: 32px; font-weight: 700; color: #0070f3; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .table-wrapper { overflow-x: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; min-width: 1200px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #0070f3; color: #fff; font-weight: 600; position: sticky; top: 0; white-space: nowrap; }
    tr:hover { background: #f8f9fa; }
    .number { text-align: right; font-family: monospace; }
    .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
    .status.pending_approval { background: #fff3cd; color: #856404; }
    .status.approved { background: #d4edda; color: #155724; }
    .status.rejected { background: #f8d7da; color: #721c24; }
    .actions { white-space: nowrap; }
    .actions a { display: inline-block; margin-right: 5px; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; }
    .actions .view-items { background: #0070f3; color: #fff; }
    .actions .view-items:hover { background: #0056b3; }
    .actions .view-approvals { background: #6f42c1; color: #fff; }
    .actions .view-approvals:hover { background: #5a32a3; }
    .actions .view-json { background: #28a745; color: #fff; }
    .actions .view-json:hover { background: #218838; }
    .empty { text-align: center; padding: 40px; color: #666; }
    .truncate { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  </style>
</head>
<body>
  <div class="container">
    <h1>📦 KPN Validation - Inbound Data <span class="badge">Next.js</span></h1>
    <p class="subtitle">Data from PostgreSQL database (sorted by newest first)</p>
    ${dbError ? `<div class="error"><strong>Database Error:</strong> ${e(dbError)}</div>` : ''}
    <div class="stats">
      <div class="stat-card">
        <h3>Total Records</h3>
        <div class="value">${headers.length}</div>
      </div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Received At</th><th>Requisition ID</th><th>Status</th>
            <th>Requested By</th><th>Ship To</th><th class="number">Total</th>
            <th>Currency</th><th>Items</th><th>Approvals</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
      </table>
    </div>
  </div>
</body>
</html>`;

  return new NextResponse(html, {
    headers: { 'Content-Type': 'text/html; charset=UTF-8' },
  });
}

async function renderItemsPage(headerId: number) {
  try {
    const headerResult = await query('SELECT * FROM inbound_headers WHERE id = $1', [headerId]);
    if (headerResult.rows.length === 0) {
      return new NextResponse('<html><body><h1>Header not found</h1></body></html>', {
        headers: { 'Content-Type': 'text/html' },
      });
    }
    const header = headerResult.rows[0];

    const itemsResult = await query(
      'SELECT * FROM inbound_items WHERE header_id = $1 ORDER BY line_num ASC',
      [headerId]
    );
    const items = itemsResult.rows;

    const itemRows = items.length === 0
      ? '<tr><td colspan="8" style="text-align:center;color:#666;">No items found</td></tr>'
      : items.map((item: Record<string, string | number>) => {
          const qty = parseFloat(String(item.quantity ?? '0')).toLocaleString('en-US', { minimumFractionDigits: 2 });
          const total = parseFloat(String(item.total ?? '0')).toLocaleString('en-US', { minimumFractionDigits: 2 });
          return `<tr>
            <td>${e(item.line_num)}</td>
            <td>${e(item.item_number ?? '-')}</td>
            <td>${e(item.description ?? '-')}</td>
            <td>${e(item.supplier_name ?? '-')}</td>
            <td>${e(item.account_code ?? '-')} - ${e(item.account_name ?? '')}</td>
            <td class="number">${qty}</td>
            <td>${e(item.uom_code ?? '-')}</td>
            <td class="number">${total}</td>
          </tr>`;
        }).join('\n');

    const headerTotal = parseFloat(String(header.total ?? '0')).toLocaleString('en-US', { minimumFractionDigits: 2 });

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Items - Requisition #${e(header.requisition_id)}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; }
    h1 { color: #333; margin-bottom: 10px; }
    .badge { display: inline-block; background: #0070f3; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px; vertical-align: middle; }
    .back-link { margin-bottom: 20px; }
    .back-link a { color: #0070f3; text-decoration: none; }
    .back-link a:hover { text-decoration: underline; }
    .header-info { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .header-info h2 { margin-top: 0; color: #333; }
    .header-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
    .header-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
    .header-item label { font-weight: 600; color: #666; font-size: 12px; display: block; margin-bottom: 4px; }
    .header-item span { color: #333; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #0070f3; color: #fff; font-weight: 600; position: sticky; top: 0; }
    tr:hover { background: #f8f9fa; }
    .number { text-align: right; font-family: monospace; }
    .file-links { margin-top: 15px; }
    .file-links a { display: inline-block; margin-right: 10px; padding: 8px 16px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; }
    .file-links a:hover { background: #218838; }
    .file-links a.xml { background: #fd7e14; }
    .file-links a.xml:hover { background: #e76b00; }
    .file-links a.csv { background: #17a2b8; }
    .file-links a.csv:hover { background: #138496; }
  </style>
</head>
<body>
  <div class="container">
    <div class="back-link"><a href="/data">&larr; Back to Data List</a></div>
    <h1>Requisition #${e(header.requisition_id)} <span class="badge">Next.js</span></h1>
    <div class="header-info">
      <h2>Header Information</h2>
      <div class="header-grid">
        <div class="header-item"><label>Status</label><span>${e(header.status ?? '-')}</span></div>
        <div class="header-item"><label>Received At</label><span>${e(header.received_at)}</span></div>
        <div class="header-item"><label>Submitted At</label><span>${e(header.submitted_at ?? '-')}</span></div>
        <div class="header-item"><label>Requested By</label><span>${e(header.requested_by_login ?? '-')}</span></div>
        <div class="header-item"><label>Ship To</label><span>${e(header.ship_to_address_name ?? '-')}</span></div>
        <div class="header-item"><label>City</label><span>${e(header.ship_to_address_city ?? '-')}</span></div>
        <div class="header-item"><label>Total</label><span>${e(header.currency_code ?? '')} ${headerTotal}</span></div>
        <div class="header-item"><label>Remote IP</label><span>${e(header.remote_ip ?? '-')}</span></div>
      </div>
      <div class="file-links">
        <a href="/data?file=${encodeURIComponent(header.json_filename)}" target="_blank">📄 View JSON</a>
        <a href="/data?file=${encodeURIComponent(header.xml_filename)}" class="xml" target="_blank">📄 View XML</a>
        <a href="/data?file=${encodeURIComponent(header.csv_filename)}" class="csv" target="_blank">📄 View CSV</a>
      </div>
    </div>
    <h2>Line Items (${items.length})</h2>
    <table>
      <thead>
        <tr><th>#</th><th>Item Number</th><th>Description</th><th>Supplier</th><th>Account</th><th>Qty</th><th>UOM</th><th class="number">Total</th></tr>
      </thead>
      <tbody>${itemRows}</tbody>
    </table>
  </div>
</body>
</html>`;

    return new NextResponse(html, {
      headers: { 'Content-Type': 'text/html; charset=UTF-8' },
    });
  } catch (err) {
    return new NextResponse(`<html><body><h1>Error: ${err instanceof Error ? err.message : 'Unknown'}</h1></body></html>`, {
      status: 500,
      headers: { 'Content-Type': 'text/html' },
    });
  }
}

async function renderApprovalsPage(headerId: number) {
  try {
    const headerResult = await query('SELECT * FROM inbound_headers WHERE id = $1', [headerId]);
    if (headerResult.rows.length === 0) {
      return new NextResponse('<html><body><h1>Header not found</h1></body></html>', {
        headers: { 'Content-Type': 'text/html' },
      });
    }
    const header = headerResult.rows[0];

    const approvalsResult = await query(
      'SELECT * FROM inbound_approvals WHERE header_id = $1 ORDER BY position ASC',
      [headerId]
    );
    const approvals = approvalsResult.rows;

    const headerTotal = parseFloat(String(header.total ?? '0')).toLocaleString('en-US', { minimumFractionDigits: 2 });

    let approvalChainHtml = '';
    if (approvals.length === 0) {
      approvalChainHtml = '<div class="empty-approvals">No approval chain data found for this requisition.</div>';
    } else {
      approvalChainHtml = '<div class="approval-chain">';
      approvals.forEach((approval: Record<string, string | number>, index: number) => {
        const statusClass = String(approval.status ?? 'pending').toLowerCase().replace(/ /g, '_');
        let circleClass = 'pending';
        if (approval.status === 'approved') circleClass = 'approved';
        if (approval.status === 'rejected') circleClass = 'rejected';

        approvalChainHtml += `<div class="approval-step">
          <div class="approval-connector">
            <div class="approval-circle ${circleClass}">${e(approval.position)}</div>
            ${index < approvals.length - 1 ? '<div class="approval-line"></div>' : ''}
          </div>
          <div class="approval-content">
            <h3>Step ${e(approval.position)} - ${e(ucwords(String(approval.status ?? 'Pending').replace(/_/g, ' ')))}</h3>
            <span class="status ${statusClass}">${e(ucwords(String(approval.status ?? '-').replace(/_/g, ' ')))}</span>
            <div class="approval-details">
              <div><span class="label">Approval ID:</span></div>
              <div><span class="value">${e(approval.approval_id ?? '-')}</span></div>
              <div><span class="label">Approval Chain ID:</span></div>
              <div><span class="value">${e(approval.approval_chain_id ?? '-')}</span></div>
            </div>
          </div>
        </div>`;
      });
      approvalChainHtml += '</div>';
    }

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approval Chain - Requisition #${e(header.requisition_id)}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { color: #333; margin-bottom: 10px; }
    .badge { display: inline-block; background: #0070f3; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px; vertical-align: middle; }
    .back-link { margin-bottom: 20px; }
    .back-link a { color: #0070f3; text-decoration: none; }
    .back-link a:hover { text-decoration: underline; }
    .header-info { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .header-info h2 { margin-top: 0; color: #333; }
    .header-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
    .header-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
    .header-item label { font-weight: 600; color: #666; font-size: 12px; display: block; margin-bottom: 4px; }
    .header-item span { color: #333; }
    .approval-chain { display: flex; flex-direction: column; gap: 0; }
    .approval-step { display: flex; align-items: flex-start; gap: 20px; }
    .approval-connector { display: flex; flex-direction: column; align-items: center; width: 40px; }
    .approval-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 16px; }
    .approval-circle.pending { background: #ffc107; color: #333; }
    .approval-circle.approved { background: #28a745; }
    .approval-circle.rejected { background: #dc3545; }
    .approval-line { width: 3px; height: 60px; background: #dee2e6; }
    .approval-content { flex: 1; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .approval-content h3 { margin: 0 0 10px 0; color: #333; font-size: 16px; }
    .approval-content .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .approval-content .status.pending_approval { background: #fff3cd; color: #856404; }
    .approval-content .status.approved { background: #d4edda; color: #155724; }
    .approval-content .status.rejected { background: #f8d7da; color: #721c24; }
    .approval-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px; font-size: 14px; }
    .approval-details .label { color: #666; }
    .approval-details .value { color: #333; }
    .empty-approvals { background: #fff; padding: 40px; text-align: center; border-radius: 8px; color: #666; }
  </style>
</head>
<body>
  <div class="container">
    <div class="back-link"><a href="/data">&larr; Back to Data List</a></div>
    <h1>🔗 Approval Chain - Requisition #${e(header.requisition_id)} <span class="badge">Next.js</span></h1>
    <div class="header-info">
      <h2>Requisition Information</h2>
      <div class="header-grid">
        <div class="header-item"><label>Status</label><span>${e(header.status ?? '-')}</span></div>
        <div class="header-item"><label>Requested By</label><span>${e(header.requested_by_login ?? '-')}</span></div>
        <div class="header-item"><label>Submitted At</label><span>${e(header.submitted_at ?? '-')}</span></div>
        <div class="header-item"><label>Total</label><span>${e(header.currency_code ?? '')} ${headerTotal}</span></div>
      </div>
    </div>
    <h2>Approval Steps (${approvals.length})</h2>
    ${approvalChainHtml}
  </div>
</body>
</html>`;

    return new NextResponse(html, {
      headers: { 'Content-Type': 'text/html; charset=UTF-8' },
    });
  } catch (err) {
    return new NextResponse(`<html><body><h1>Error: ${err instanceof Error ? err.message : 'Unknown'}</h1></body></html>`, {
      status: 500,
      headers: { 'Content-Type': 'text/html' },
    });
  }
}

// Helper: escape HTML
function e(val: unknown): string {
  return String(val ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// Helper: uppercase first letter of each word
function ucwords(str: string): string {
  return str.replace(/\b\w/g, (c) => c.toUpperCase());
}
