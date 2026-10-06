import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.QDB_BASE_URL || '').replace(/\/$/, '');
const staticVus = Number(__ENV.QDB_STATIC_VUS || '1');
const dynamicVus = Number(__ENV.QDB_DYNAMIC_VUS || '0');
const duration = __ENV.QDB_DURATION || '30s';
const p95Milliseconds = Number(__ENV.QDB_P95_MILLISECONDS || '1000');
const maximumErrorRate = Number(__ENV.QDB_MAX_ERROR_RATE || '0.01');
const numericQuotePath = __ENV.QDB_NUMERIC_QUOTE_PATH || '/1';
const canonicalQuotePath = __ENV.QDB_CANONICAL_QUOTE_PATH || '';

if (baseUrl === '') {
  throw new Error('Set QDB_BASE_URL to the approved staging origin before running this probe.');
}
if (!Number.isInteger(staticVus) || staticVus < 1) {
  throw new Error('QDB_STATIC_VUS must be a positive integer.');
}
if (!Number.isInteger(dynamicVus) || dynamicVus < 0) {
  throw new Error('QDB_DYNAMIC_VUS must be a non-negative integer.');
}
if (!Number.isFinite(p95Milliseconds) || p95Milliseconds <= 0) {
  throw new Error('QDB_P95_MILLISECONDS must be a positive number.');
}
if (!Number.isFinite(maximumErrorRate) || maximumErrorRate < 0 || maximumErrorRate >= 1) {
  throw new Error('QDB_MAX_ERROR_RATE must be at least zero and less than one.');
}

const staticPaths = [
  '/',
  '/latest',
  '/top',
  '/leetness',
  numericQuotePath,
  ...(canonicalQuotePath === '' ? [] : [canonicalQuotePath]),
  '/assets/site.css',
];

export const options = {
  scenarios: {
    anonymous_static: {
      executor: 'constant-vus',
      exec: 'anonymousStaticRead',
      vus: staticVus,
      duration,
    },
    ...(dynamicVus === 0 ? {} : {
      php_fallback: {
        executor: 'constant-vus',
        exec: 'phpFallbackRead',
        vus: dynamicVus,
        duration,
      },
    }),
  },
  thresholds: {
    http_req_failed: [`rate<${maximumErrorRate}`],
    http_req_duration: [`p(95)<${p95Milliseconds}`],
  },
};

export function anonymousStaticRead() {
  const path = staticPaths[__ITER % staticPaths.length];
  const response = http.get(baseUrl + path, {
    tags: { capacity_path: 'anonymous_static' },
  });

  check(response, {
    'anonymous static read succeeds': (result) => result.status === 200,
  });
  sleep(0.2);
}

export function phpFallbackRead() {
  const requests = [
    '/latest',
    '/search?search=qdb',
  ];
  const path = requests[__ITER % requests.length];
  const response = http.get(baseUrl + path, {
    headers: { Cookie: 'qdb_capacity_probe=1' },
    tags: { capacity_path: 'php_fallback' },
  });

  check(response, {
    'PHP fallback read succeeds': (result) => result.status === 200,
  });
  sleep(0.5);
}
