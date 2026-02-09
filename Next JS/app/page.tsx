export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-white flex items-center justify-center p-8">
      <div className="max-w-4xl w-full">
        <div className="text-center mb-12">
          <h1 className="text-5xl font-bold mb-4">
            📦 KPN Validation Test
          </h1>
          <p className="text-xl text-gray-300">
            Next.js API — Inbound Data Processing &amp; PostgreSQL Storage
          </p>
          <div className="mt-4 inline-block bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-semibold">
            Next.js v16 + TypeScript + PostgreSQL
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-6 mb-12">
          <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:border-blue-500 transition-colors">
            <h2 className="text-lg font-semibold mb-3 text-blue-400">📥 POST /</h2>
            <p className="text-gray-300 text-sm mb-3">Save inbound data with Basic Auth. Stores to JSON, XML, CSV files + PostgreSQL.</p>
            <code className="text-xs bg-gray-900 text-green-400 px-3 py-2 rounded block">
              curl -X POST -u yossy:yossy -H &quot;Content-Type: application/json&quot; -d &apos;...&apos;
            </code>
          </div>

          <a href="/data" className="bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:border-blue-500 transition-colors block">
            <h2 className="text-lg font-semibold mb-3 text-blue-400">📊 GET /data</h2>
            <p className="text-gray-300 text-sm mb-3">View all inbound records in an HTML table. Click items &amp; approvals for detail.</p>
            <span className="text-xs bg-blue-600 text-white px-3 py-1 rounded">
              Open Data Viewer →
            </span>
          </a>

          <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:border-blue-500 transition-colors">
            <h2 className="text-lg font-semibold mb-3 text-blue-400">🔌 JSON API</h2>
            <p className="text-gray-300 text-sm mb-3">RESTful JSON endpoints for programmatic access.</p>
            <div className="space-y-1 text-xs text-gray-400">
              <div><code>GET /api/headers</code> — List all</div>
              <div><code>GET /api/headers/:id</code> — Detail</div>
              <div><code>GET /api/headers/:id/items</code> — Items</div>
              <div><code>GET /api/headers/:id/approvals</code> — Approvals</div>
            </div>
          </div>

          <div className="bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:border-blue-500 transition-colors">
            <h2 className="text-lg font-semibold mb-3 text-blue-400">🗄️ Database</h2>
            <p className="text-gray-300 text-sm mb-3">PostgreSQL with 4 tables for structured storage.</p>
            <div className="space-y-1 text-xs text-gray-400">
              <div>• <code>inbound_headers</code> — Header records</div>
              <div>• <code>inbound_items</code> — Line items</div>
              <div>• <code>inbound_approvals</code> — Approval chain</div>
              <div>• <code>rfc_call_history</code> — SAP RFC log</div>
            </div>
          </div>
        </div>

        <div className="text-center text-gray-500 text-sm">
          <p>Running on port 3001 • Domain: kpn-validation-test-nextjs.ilmuprogram.app</p>
          <p className="mt-1">Powered by Next.js + Tailwind CSS + PostgreSQL</p>
        </div>
      </div>
    </div>
  );
}
