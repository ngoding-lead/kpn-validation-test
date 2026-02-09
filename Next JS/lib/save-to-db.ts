/**
 * @file    Database insert logic (headers, items, approvals, RFC history)
 * @module  lib/save-to-db
 * @author  Wahyu Amaldi — Technical Lead, KPMG
 * @version 1.0.0
 */

import { getClient } from './db';
import { parseTimestamp } from './utils';

interface Metadata {
  received_at: string;
  remote_ip: string;
  user_agent: string;
  content_type: string;
}

/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Save inbound data to PostgreSQL (headers, items, approvals)
 */
export async function saveToDatabase(
  metadata: Metadata,
  postData: any,
  jsonFilename: string,
  xmlFilename: string,
  csvFilename: string
): Promise<number | false> {
  const client = await getClient();

  try {
    await client.query('BEGIN');

    const data = postData;
    const fileId = jsonFilename.replace('.json', '');

    // Insert header
    const headerResult = await client.query(
      `INSERT INTO inbound_headers (
        file_id, received_at, remote_ip, user_agent, requisition_id,
        created_at, updated_at, status, submitted_at, ship_to_attention,
        exported, total, total_with_estimated_tax, estimated_tax_amount,
        rejected, currency_code, requested_by_id, requested_by_login,
        ship_to_address_id, ship_to_address_name, ship_to_address_city,
        ship_to_address_street1, buyer_note, justification,
        json_filename, xml_filename, csv_filename
      ) VALUES (
        $1, $2, $3, $4, $5,
        $6, $7, $8, $9, $10,
        $11, $12, $13, $14,
        $15, $16, $17, $18,
        $19, $20, $21,
        $22, $23, $24,
        $25, $26, $27
      ) RETURNING id`,
      [
        fileId,
        metadata.received_at,
        metadata.remote_ip,
        metadata.user_agent,
        data['id'] ?? null,
        parseTimestamp(data['created-at']),
        parseTimestamp(data['updated-at']),
        data['status'] ?? null,
        parseTimestamp(data['submitted-at']),
        data['ship-to-attention'] ?? null,
        data['exported'] ? true : false,
        data['total'] ?? null,
        data['total-with-estimated-tax'] ?? null,
        data['estimated-tax-amount'] ?? null,
        data['rejected'] ? true : false,
        data['currency']?.['code'] ?? null,
        data['requested-by']?.['id'] ?? null,
        data['requested-by']?.['login'] ?? null,
        data['ship-to-address']?.['id'] ?? null,
        data['ship-to-address']?.['name'] ?? null,
        data['ship-to-address']?.['city'] ?? null,
        data['ship-to-address']?.['street1'] ?? null,
        data['buyer-note'] ?? null,
        data['justification'] ?? null,
        jsonFilename,
        xmlFilename,
        csvFilename,
      ]
    );

    const headerId = headerResult.rows[0].id;

    // Insert items (requisition-lines)
    if (data['requisition-lines'] && Array.isArray(data['requisition-lines'])) {
      for (const line of data['requisition-lines']) {
        await client.query(
          `INSERT INTO inbound_items (
            header_id, line_id, line_num, description, quantity, total,
            source_part_num, status, item_id, item_number, item_name,
            supplier_id, supplier_name, supplier_number, uom_code,
            account_id, account_name, account_code, created_at
          ) VALUES (
            $1, $2, $3, $4, $5, $6,
            $7, $8, $9, $10, $11,
            $12, $13, $14, $15,
            $16, $17, $18, $19
          )`,
          [
            headerId,
            line['id'] ?? null,
            line['line-num'] ?? null,
            line['description'] ?? null,
            line['quantity'] ?? null,
            line['total'] ?? null,
            line['source-part-num'] ?? null,
            line['status'] ?? null,
            line['item']?.['id'] ?? null,
            line['item']?.['item-number'] ?? null,
            line['item']?.['name'] ?? null,
            line['supplier']?.['id'] ?? null,
            line['supplier']?.['name'] ?? null,
            line['supplier']?.['number'] ?? null,
            line['uom']?.['code'] ?? null,
            line['account']?.['id'] ?? null,
            line['account']?.['name'] ?? null,
            line['account']?.['code'] ?? null,
            parseTimestamp(line['created-at']),
          ]
        );
      }
    }

    // Insert approvals
    if (data['approvals'] && Array.isArray(data['approvals'])) {
      for (const approval of data['approvals']) {
        await client.query(
          `INSERT INTO inbound_approvals (
            header_id, approval_id, position, approval_chain_id, status,
            approval_date, note, type, approvable_type, approvable_id,
            parallel_group_name, delegate_id, approved_by_id, approved_by_login,
            approved_by_email, created_at, updated_at
          ) VALUES (
            $1, $2, $3, $4, $5,
            $6, $7, $8, $9, $10,
            $11, $12, $13, $14,
            $15, $16, $17
          )`,
          [
            headerId,
            approval['id'] ?? null,
            approval['position'] ?? null,
            approval['approval-chain-id'] ?? null,
            approval['status'] ?? null,
            parseTimestamp(approval['approval-date']),
            approval['note'] ?? null,
            approval['type'] ?? null,
            approval['approvable-type'] ?? null,
            approval['approvable-id'] ?? null,
            approval['parallel-group-name'] ?? null,
            approval['delegate-id'] ?? null,
            approval['approved-by']?.['id'] ?? null,
            approval['approved-by']?.['login'] ?? null,
            approval['approved-by']?.['email'] ?? null,
            parseTimestamp(approval['created-at']),
            parseTimestamp(approval['updated-at']),
          ]
        );
      }
    }

    await client.query('COMMIT');

    // Record RFC call history (SAP RFC call - logged but not actually called since no SAPNWRFC in Node)
    if (headerId && data['requisition-lines']?.length > 0) {
      const rfcData = data['requisition-lines'].map((line: any) => ({
        REQUESTED_BY_ID: String(data['requested-by']?.['id'] ?? ''),
        LINE_ID: String(line['id'] ?? ''),
      }));

      try {
        await client.query(
          `INSERT INTO rfc_call_history 
           (header_id, function_module, request_data, response_data, success, error_message, execution_time_ms)
           VALUES ($1, $2, $3, $4, $5, $6, $7)`,
          [
            headerId,
            'ZKPN_TEST',
            JSON.stringify({ T_DATA: rfcData }),
            JSON.stringify({ message: 'RFC not available in Next.js - logged for reference' }),
            false,
            'SAP NWRFC not available in Node.js environment',
            0,
          ]
        );
      } catch (rfcErr) {
        console.error('Failed to record RFC history:', rfcErr);
      }
    }

    return headerId;
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Database save error:', err);
    return false;
  } finally {
    client.release();
  }
}
