const url = process.argv[2] || 'http://127.0.0.1:8000/api/v1/health';
const total = Math.max(1, Number(process.argv[3] || 100));
const concurrency = Math.max(1, Math.min(total, Number(process.argv[4] || 10)));

let cursor = 0;
let failures = 0;
const durations = [];
const startedAt = performance.now();

async function worker() {
  while (true) {
    const index = cursor++;
    if (index >= total) return;
    const start = performance.now();
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      await response.arrayBuffer();
      if (!response.ok) failures++;
    } catch {
      failures++;
    } finally {
      durations.push(performance.now() - start);
    }
  }
}

await Promise.all(Array.from({ length: concurrency }, () => worker()));
const elapsed = performance.now() - startedAt;
durations.sort((a, b) => a - b);
const percentile = (p) => durations[Math.min(durations.length - 1, Math.ceil(durations.length * p) - 1)] || 0;

console.log(JSON.stringify({
  url,
  requests: total,
  concurrency,
  successes: total - failures,
  failures,
  p50_ms: Number(percentile(0.50).toFixed(2)),
  p95_ms: Number(percentile(0.95).toFixed(2)),
  max_ms: Number((durations.at(-1) || 0).toFixed(2)),
  throughput_rps: Number((total / (elapsed / 1000)).toFixed(2)),
}, null, 2));

if (failures > 0) process.exit(1);
