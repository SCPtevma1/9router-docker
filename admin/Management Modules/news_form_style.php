* { box-sizing: border-box; }
body { display: grid; grid-template-columns: 250px minmax(0, 1fr); min-height: 100vh; margin: 0; background: #f1f4f1; color: #202a26; font-family: "Segoe UI", "Leelawadee UI", Tahoma, sans-serif; }
.admin-header { position: sticky; top: 0; display: flex; height: 100vh; padding: 1.5rem 1.25rem; flex-direction: column; align-items: stretch; gap: 2rem; background: #1d2925; color: #fff; }
.admin-brand { display: flex; align-items: center; gap: .75rem; color: #fff; font-weight: 700; line-height: 1.35; text-decoration: none; }
.admin-brand img { width: 42px; height: 42px; flex: 0 0 auto; border: 1px solid rgba(255,255,255,.45); border-left: 3px solid #bd4650; border-radius: 3px; background: #fff; object-fit: contain; }
.admin-header nav { display: grid; gap: .35rem; }
.admin-header nav a { padding: .65rem .75rem; border-radius: 4px; color: rgba(255,255,255,.82); text-decoration: none; transition: background-color .16s ease; }
.admin-header nav a:hover, .admin-header nav a:focus-visible, .admin-header nav a[aria-current="page"] { border-left: 3px solid #d29064; background: rgba(255,255,255,.12); color: #fff; }
.admin-header nav a:focus-visible, .admin-brand:focus-visible, .admin-user a:focus-visible { outline: 2px solid #d29064; outline-offset: 2px; }
.admin-user { display: grid; gap: .25rem; margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.2); overflow-wrap: anywhere; }
.admin-user small { color: rgba(255,255,255,.68); }
.admin-user a { margin-top: .35rem; color: #fff; }
.box { width: min(760px, calc(100% - 4rem)); margin: 2rem auto; padding: 1.5rem; border: 1px solid #d9dfda; border-top: 4px solid #a5323d; border-radius: 6px; background: #fff; box-shadow: 0 8px 24px rgba(22,35,29,.07); }
h1 { margin-top: 0; color: #202a26; font-size: 1.45rem; }
label { display: block; margin: .8rem 0 .3rem; color: #33443a; font-weight: 700; }
input, textarea, select { width: 100%; padding: .65rem; border: 1px solid #cbd5ce; border-radius: 4px; background: #fff; color: #202a26; font: inherit; }
input:focus-visible, textarea:focus-visible, select:focus-visible, a:focus-visible, button:focus-visible { outline: 3px solid rgba(165,50,61,.28); outline-offset: 2px; }
textarea { min-height: 120px; resize: vertical; }
button, .button { display: inline-block; margin-top: 1rem; padding: .65rem .9rem; border: 1px solid #a5323d; border-radius: 4px; background: #a5323d; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
button:hover, .button:hover { border-color: #842933; background: #842933; }
.secondary { border-color: #cbd5ce; background: #fff; color: #33443a; }
.error { padding: .7rem; border: 1px solid #e5c3c4; border-left: 3px solid #a5323d; border-radius: 4px; background: #fff5f3; color: #812f29; }
.hint { margin: .35rem 0; color: #66788a; font-size: .88rem; }
.image-previews { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: .75rem; }
.image-preview { width: 125px; display: grid; gap: .35rem; font-size: .82rem; }
.image-preview img { width: 125px; height: 90px; border-radius: 4px; object-fit: cover; }
.image-preview label { display: flex; align-items: center; gap: .3rem; margin: 0; font-weight: 400; }
.image-preview input { width: auto; }
@media (max-width: 760px) {
	body { grid-template-columns: 1fr; }
	.admin-header { position: static; height: auto; padding: 1rem; gap: .9rem; }
	.admin-header nav { display: flex; flex-wrap: wrap; gap: .25rem; }
	.admin-user { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .75rem; margin: 0; padding-top: .7rem; }
	.admin-user a { margin: 0 0 0 auto; }
	.box { width: min(100% - 2rem, 760px); margin: 1rem auto; padding: 1rem; }
}