import { Pool, PoolClient } from 'pg';

const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '5432'),
  database: process.env.DB_NAME || 'kpn_validation_test',
  user: process.env.DB_USER || 'postgres',
  password: process.env.DB_PASS,
  max: 10,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000,
});

export default pool;

export async function query(text: string, params?: unknown[]) {
  const client = await pool.connect();
  try {
    const result = await client.query(text, params);
    return result;
  } finally {
    client.release();
  }
}

export async function getClient(): Promise<PoolClient> {
  return pool.connect();
}
