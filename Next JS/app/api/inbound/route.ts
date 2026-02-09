/**
 * @file    POST /api/inbound — Receive and store inbound data
 * @module  api/inbound
 * @author  Wahyu Amaldi — Technical Lead, KPMG
 * @version 1.0.0
 */

import { NextRequest, NextResponse } from 'next/server';
import { verifyBasicAuth } from '@/lib/auth';
import { saveToDatabase } from '@/lib/save-to-db';
import {
  jsonToXml,
  flattenObject,
  objectToCsv,
  formatDate,
  uniqueId,
} from '@/lib/utils';
import { writeFileSync, mkdirSync, existsSync } from 'fs';
import path from 'path';

const INBOUND_DIR = path.join(process.cwd(), 'inbound');

/**
 * POST /api/inbound - Save inbound data
 * Requires Basic Auth
 * Saves to JSON, XML, CSV files + PostgreSQL database
 */
export async function POST(request: NextRequest) {
  // Verify Basic Auth
  const auth = verifyBasicAuth(request);
  if (!auth.valid) {
    return NextResponse.json(
      {
        success: false,
        message: auth.error,
        timestamp: formatDate(),
      },
      {
        status: auth.error === 'Invalid credentials.' ? 403 : 401,
        headers:
          auth.error === 'Authentication required.'
            ? { 'WWW-Authenticate': 'Basic realm="KPN Validation API"' }
            : {},
      }
    );
  }

  // Parse body
  let postData;
  try {
    const rawBody = await request.text();
    if (!rawBody || rawBody.trim() === '') {
      return NextResponse.json(
        {
          success: false,
          message: 'Request body is empty.',
          timestamp: formatDate(),
        },
        { status: 400 }
      );
    }
    postData = JSON.parse(rawBody);
  } catch {
    return NextResponse.json(
      {
        success: false,
        message: 'Invalid JSON body.',
        timestamp: formatDate(),
      },
      { status: 400 }
    );
  }

  // Ensure inbound directory exists
  if (!existsSync(INBOUND_DIR)) {
    mkdirSync(INBOUND_DIR, { recursive: true });
  }

  // Generate filenames
  const timestamp = formatDate('file');
  const uid = uniqueId();
  const baseFilename = `inbound_${timestamp}_${uid}`;

  // Metadata
  const metadata = {
    received_at: formatDate(),
    remote_ip:
      request.headers.get('x-forwarded-for') ||
      request.headers.get('x-real-ip') ||
      'unknown',
    user_agent: request.headers.get('user-agent') || 'unknown',
    content_type: request.headers.get('content-type') || 'unknown',
  };

  // 1. Save JSON
  const jsonFilename = `${baseFilename}.json`;
  const jsonContent = JSON.stringify(
    { _metadata: metadata, data: postData },
    null,
    2
  );
  writeFileSync(path.join(INBOUND_DIR, jsonFilename), jsonContent, 'utf-8');

  // 2. Save XML
  const xmlFilename = `${baseFilename}.xml`;
  const xmlContent = jsonToXml({ _metadata: metadata, data: postData });
  writeFileSync(path.join(INBOUND_DIR, xmlFilename), xmlContent, 'utf-8');

  // 3. Save CSV
  const csvFilename = `${baseFilename}.csv`;
  const flattenedData = {
    _metadata_received_at: metadata.received_at,
    _metadata_remote_ip: metadata.remote_ip,
    _metadata_user_agent: metadata.user_agent,
    _metadata_content_type: metadata.content_type,
    ...flattenObject(postData),
  };
  const csvContent = objectToCsv(flattenedData);
  writeFileSync(path.join(INBOUND_DIR, csvFilename), csvContent, 'utf-8');

  // 4. Save to PostgreSQL
  try {
    const dbResult = await saveToDatabase(
      metadata,
      postData,
      jsonFilename,
      xmlFilename,
      csvFilename
    );

    return NextResponse.json({
      success: true,
      message: 'Data saved successfully.',
      database_id: dbResult || null,
      files: {
        json: jsonFilename,
        xml: xmlFilename,
        csv: csvFilename,
      },
      timestamp: formatDate(),
      data_received: postData,
    });
  } catch (err) {
    console.error('Save error:', err);
    return NextResponse.json(
      {
        success: false,
        message: `Failed to save data: ${err instanceof Error ? err.message : 'Unknown error'}`,
        timestamp: formatDate(),
      },
      { status: 500 }
    );
  }
}

/**
 * GET /api/inbound - API Info
 */
export async function GET() {
  return NextResponse.json({
    app: 'KPN Validation Test API (Next.js)',
    version: '1.0.0',
    endpoints: {
      'POST /api/inbound': 'Save inbound data (requires Basic Auth)',
      'GET /data': 'View data table (HTML)',
      'GET /data?file={filename}': 'Download/view file',
      'GET /data?items={id}': 'View items for header',
      'GET /data?approvals={id}': 'View approval chain',
      'GET /api/headers': 'API: List all headers (JSON)',
      'GET /api/headers/{id}': 'API: Get header detail (JSON)',
      'GET /api/headers/{id}/items': 'API: Get items for header (JSON)',
      'GET /api/headers/{id}/approvals': 'API: Get approvals for header (JSON)',
    },
    timestamp: formatDate(),
  });
}
