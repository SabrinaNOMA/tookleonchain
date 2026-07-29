<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Tookle — KYC/AML Admin</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;background:#fff;color:#111;margin:0}
header{display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid #eee}
.wrap{max-width:1100px;margin:20px auto;padding:0 16px}
.row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
input,button{padding:10px 12px;border-radius:10px;border:1px solid #ddd}
button{background:#2151ff;color:#fff;border-color:#2151ff;cursor:pointer}
.pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid #ddd;background:#f6f7fb;margin-right:6px}
.ok{color:#16a34a;border-color:rgba(22,163,74,.3);background:rgba(22,163,74,.08)}
.ko{color:#dc2626;border-color:rgba(220,38,38,.3);background:rgba(220,38,38,.08)}
.pending{color:#ca8a04;border-color:rgba(234,179,8,.3);background:rgba(234,179,8,.08)}
table{width:100%;border-collapse:collapse;margin-top:10px}
td,th{border:1px solid #eee;padding:8px;text-align:left}
pre{background:#0b0f19;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto}
</style>
</head>
<body>
<header><strong>Tookle Admin</strong><a href="./kyc_flow.html">Open WebSDK Flow</a></header>
<div class="wrap">
  <div class="row">
    <input id="applicantId" placeholder="applicantId" style="min-width:260px" />
    <input id="user" placeholder="externalUserId" style="min-width:260px" />
    <button onclick="loadAll()">Load</button>
    <button onclick="refresh()">Refresh live (pull Sumsub)</button>
  </div>
  <div id="pills"></div>
  <h3>Uploads</h3>
  <div id="uploads">Use your DB list here (hook to your kyc_uploads).</div>
  <h3>Raw JSON</h3>
  <pre id="raw">—</pre>
</div>
<script>
async function q(sel){return document.querySelector(sel)}
async function getJson(url){const r=await fetch(url);return r.json()}
async function loadAll(){
  const id=(await q('#applicantId')).value.trim();
  const user=(await q('#user')).value.trim();
  let aid=id;
  if(!aid && user){
    // resolve using sync endpoint to also store
    const s=await getJson('./sync_kyc_aml.php?user='+encodeURIComponent(user));
    if(!s.ok){ alert('Resolve failed'); return; }
    aid=s.applicantId; (await q('#applicantId')).value = aid;
  }
  if(!aid){ alert('Provide applicantId or externalUserId'); return; }
  const kyc=await getJson('./kyc_status.php?applicantId='+encodeURIComponent(aid));
  const aml=await getJson('./aml_status.php?applicantId='+encodeURIComponent(aid));
  const pills = [];
  const rs = kyc.data?.review_status, ra=kyc.data?.review_answer;
  if(rs==='completed' && ra==='GREEN') pills.push('<span class="pill ok">KYC Approved</span>');
  else if(rs==='completed' && ra==='RED') pills.push('<span class="pill ko">KYC Rejected</span>');
  else pills.push('<span class="pill pending">KYC In Review</span>');
  if(aml.data){
    const S=aml.data.sanctions_result||'N/A', P=aml.data.pep_result||'N/A', A=aml.data.adverse_result||'N/A';
    pills.push('<span class="pill">Sanctions: '+S+'</span>');
    pills.push('<span class="pill">PEP: '+P+'</span>');
    pills.push('<span class="pill">Adverse: '+A+'</span>');
  }
  (await q('#pills')).innerHTML = pills.join(' ');
  (await q('#raw')).textContent = JSON.stringify({kyc:kyc, aml:aml}, null, 2);
}
async function refresh(){
  const id=(await q('#applicantId')).value.trim();
  const user=(await q('#user')).value.trim();
  let url = './sync_kyc_aml.php?';
  url += id ? 'applicantId='+encodeURIComponent(id) : 'user='+encodeURIComponent(user);
  const s=await getJson(url);
  if(!s.ok){ alert('Sync failed'); return; }
  await loadAll();
}
</script>
</body></html>
