/**
 * @file    Basic Auth verification middleware
 * @module  lib/auth
 * @author  Wahyu Amaldi — Technical Lead, KPMG
 * @version 1.0.0
 */

import { NextRequest } from 'next/server';

const AUTH_USERNAME = process.env.AUTH_USERNAME || 'yossy';
const AUTH_PASSWORD = process.env.AUTH_PASSWORD || 'yossy';

export function verifyBasicAuth(request: NextRequest): { valid: boolean; error?: string } {
  const authHeader = request.headers.get('authorization');

  if (!authHeader || !authHeader.startsWith('Basic ')) {
    return { valid: false, error: 'Authentication required.' };
  }

  const base64Credentials = authHeader.split(' ')[1];
  const credentials = Buffer.from(base64Credentials, 'base64').toString('utf-8');
  const [username, password] = credentials.split(':');

  if (!username || !password) {
    return { valid: false, error: 'Authentication required.' };
  }

  if (username !== AUTH_USERNAME || password !== AUTH_PASSWORD) {
    return { valid: false, error: 'Invalid credentials.' };
  }

  return { valid: true };
}
