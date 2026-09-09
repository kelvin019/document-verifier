* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f4; color: #1a1a1a; }
a { color: #2d7a3a; text-decoration: none; }
a:hover { text-decoration: underline; }
code { font-family: monospace; font-size: 12px; background: #f0f4f0; padding: 2px 5px; border-radius: 4px; }

/* Nav */
.nav {
  background: #1a4a0d; color: #fff;
  display: flex; align-items: center; gap: 0;
  padding: 0 1.5rem; height: 52px;
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 15px; color: #7dd3aa; margin-right: auto; }
.nav-brand .logo-dot { width: 28px; height: 28px; border-radius: 6px; background: #2d6b18; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #7dd3aa; font-weight: 700; }
.nav a { color: #c8e6c9; font-size: 13px; padding: 0 14px; height: 52px; display: flex; align-items: center; text-decoration: none; transition: background 0.15s; }
.nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.nav a.active { background: rgba(255,255,255,0.12); color: #fff; }
.nav-sep { color: rgba(255,255,255,0.2); margin: 0 4px; }

/* Page layout */
.page { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-header h1 { font-size: 20px; font-weight: 700; }

/* Cards */
.card { background: #fff; border-radius: 12px; border: 1px solid #e2e8e2; margin-bottom: 1.5rem; overflow: hidden; }
.card-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid #eaeeea; }
.card-header h2 { font-size: 14px; font-weight: 600; }
.card-body { padding: 1.5rem; }
.card-body + .card-body { border-top: 1px solid #eaeeea; }

/* Stats */
.stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { flex: 1; background: #fff; border: 1px solid #e2e8e2; border-radius: 12px; padding: 1.25rem 1.5rem; }
.stat-num { font-size: 32px; font-weight: 700; color: #1a4a0d; }
.stat-label { font-size: 12px; color: #6b7a6b; margin-top: 2px; }

/* Table */
table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 11px; font-weight: 600; color: #6b7a6b; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 1.5rem; border-bottom: 1px solid #eaeeea; background: #fafbfa; }
td { padding: 12px 1.5rem; font-size: 13px; border-bottom: 1px solid #f0f4f0; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafbfa; }
td.actions { white-space: nowrap; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 5px;
  background: #1a4a0d; color: #fff; border: none; border-radius: 7px;
  padding: 8px 16px; font-size: 13px; font-weight: 500; cursor: pointer;
  text-decoration: none; transition: background 0.15s; white-space: nowrap;
}
.btn:hover { background: #2d6b18; color: #fff; text-decoration: none; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.btn-secondary { background: #f0f4f0; color: #2d4a2d; }
.btn-secondary:hover { background: #e0e8e0; color: #2d4a2d; }
.btn-danger { background: #c0392b; }
.btn-danger:hover { background: #a93226; }
.btn-outline { background: transparent; color: #1a4a0d; border: 1px solid #1a4a0d; }
.btn-outline:hover { background: #1a4a0d; color: #fff; }

/* Forms */
.form-group { margin-bottom: 1.1rem; }
.form-group label { display: block; font-size: 12px; font-weight: 500; color: #4a5a4a; margin-bottom: 5px; }
.form-group input[type=text],
.form-group input[type=password],
.form-group select,
.form-group textarea {
  width: 100%; border: 1px solid #d0d8d0; border-radius: 8px;
  padding: 9px 13px; font-size: 13px; color: #1a1a1a; outline: none;
  transition: border-color 0.2s; background: #fff; font-family: inherit;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: #2d7a3a; }
.form-group textarea { resize: vertical; min-height: 70px; }
.form-hint { font-size: 11px; color: #9aaa9a; margin-top: 3px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* Alerts */
.alert { border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 1rem; }
.alert-success { background: #f0faf3; border: 1px solid #a8d8b0; color: #1a6b30; }
.alert-error   { background: #fff4f4; border: 1px solid #f0b0b0; color: #7a3030; }
.alert-info    { background: #f0f6ff; border: 1px solid #b0c8f0; color: #304878; }

/* Empty state */
.empty { padding: 2rem 1.5rem; text-align: center; color: #6b7a6b; font-size: 13px; }

/* Badge */
.badge { display: inline-block; background: #e8f5e9; color: #2d6b18; border-radius: 20px; padding: 2px 8px; font-size: 11px; font-weight: 600; }

/* Search bar */
.search-bar { display: flex; gap: 8px; margin-bottom: 1rem; }
.search-bar input { flex: 1; border: 1px solid #d0d8d0; border-radius: 8px; padding: 8px 13px; font-size: 13px; outline: none; }
.search-bar input:focus { border-color: #2d7a3a; }

/* Section divider */
.section-title { font-size: 13px; font-weight: 600; color: #4a5a4a; margin-bottom: 1rem; padding-bottom: 6px; border-bottom: 1px solid #eaeeea; }

/* File upload area */
.upload-area {
  border: 2px dashed #c8d8c8; border-radius: 10px; padding: 1.5rem;
  text-align: center; cursor: pointer; transition: border-color 0.2s; background: #fafbfa;
}
.upload-area:hover { border-color: #2d7a3a; }
.upload-area input[type=file] { display: none; }
.upload-area p { font-size: 13px; color: #6b7a6b; margin-top: 6px; }
