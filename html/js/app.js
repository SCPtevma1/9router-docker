/* Example University — i18n, search, navigation, and reveal effects */
const DICT = {
  th: {
    top_personnel: "บุคลากร", top_student: "นักศึกษา", top_alumni: "ศิษย์เก่า", top_login: "เข้าสู่ระบบ",
    search_placeholder: "ค้นหา...", search_btn: "ค้นหา",
    brand_title: "มหาวิทยาลัยราชภัฏร้อยเอ็ด", brand_subtitle: "EXAMPLE UNIVERSITY", brand_desc: "มหาวิทยาลัยเพื่อชุมชน · นวัตกรรมเพื่อสังคม",
    nav_home: "หน้าหลัก",
    nav_about: "เกี่ยวกับมหาวิทยาลัย", nav_about_history: "ประวัติและความเป็นมา", nav_about_vision: "วิสัยทัศน์ พันธกิจ",
    nav_about_structure: "โครงสร้างองค์กร", nav_about_leader: "ผู้บริหาร", nav_about_map: "แผนที่มหาวิทยาลัย",
    nav_programs: "หลักสูตร/การศึกษา", nav_programs_bachelor: "ปริญญาตรี", nav_programs_master: "ปริญญาโท-เอก",
    nav_programs_short: "หลักสูตรระยะสั้น", nav_programs_faculty: "คณะ / วิทยาลัย", nav_programs_calendar: "ปฏิทินการศึกษา",
    nav_admission: "การรับสมัครเข้าศึกษา", nav_admission_tcas: "TCAS / รอบรับสมัคร", nav_admission_quota: "โควตา / พื้นที่",
    nav_admission_scholar: "ทุนการศึกษา", nav_admission_guide: "คู่มือการสมัคร",
    nav_news: "ข่าวสารและกิจกรรม", nav_news_uni: "ข่าวมหาวิทยาลัย", nav_news_faculty: "ข่าวคณะ",
    nav_news_event: "กิจกรรม / ปฏิทินกิจกรรม", nav_news_announce: "ประกาศ",
    nav_intranet: "ระบบสารสนเทศภายใน", nav_contact: "ติดต่อเรา",
    hero_kicker: "เปิดรับสมัคร ภาคเรียนที่ 1/2569",
    hero_title_1: "เรียนรู้เพื่ออนาคต", hero_title_2: "สร้างนวัตกรรมเพื่อสังคม",
    hero_desc: "มหาวิทยาลัยตัวอย่าง มุ่งพัฒนาบัณฑิตที่มีคุณภาพ มีคุณธรรม พร้อมทักษะแห่งศตวรรษที่ 21 เชื่อมโยงการเรียนรู้กับชุมชนและอุตสาหกรรม",
    hero_cta_primary: "สมัครเรียนออนไลน์", hero_cta_ghost: "ดูหลักสูตรทั้งหมด",
    hero_card_title: "ข่าวเด่น",
    breadcrumb_home: "หน้าหลัก",
    footer_about: "มหาวิทยาลัยตัวอย่าง",
    footer_about_desc: "มุ่งมั่นผลิตบัณฑิตคุณภาพ สร้างงานวิจัยที่ตอบโจทย์สังคม และบริการวิชาการเพื่อการพัฒนาที่ยั่งยืน",
    all_rights: "สงวนลิขสิทธิ์ © 2026 มหาวิทยาลัยตัวอย่าง",
  },
  en: {
    top_personnel: "Staff", top_student: "Students", top_alumni: "Alumni", top_login: "Login",
    search_placeholder: "Search...", search_btn: "Search",
    brand_title: "Roi Et Rajabhat University", brand_subtitle: "ROI ET RAJABHAT UNIVERSITY", brand_desc: "University for Community · Innovation for Society",
    nav_home: "Home",
    nav_about: "About", nav_about_history: "History", nav_about_vision: "Vision & Mission",
    nav_about_structure: "Organization", nav_about_leader: "Leadership", nav_about_map: "Campus Map",
    nav_programs: "Programs", nav_programs_bachelor: "Bachelor's", nav_programs_master: "Graduate",
    nav_programs_short: "Short Courses", nav_programs_faculty: "Faculties", nav_programs_calendar: "Academic Calendar",
    nav_admission: "Admissions", nav_admission_tcas: "TCAS / Rounds", nav_admission_quota: "Quota",
    nav_admission_scholar: "Scholarships", nav_admission_guide: "How to Apply",
    nav_news: "News & Events", nav_news_uni: "University News", nav_news_faculty: "Faculty News",
    nav_news_event: "Events", nav_news_announce: "Announcements",
    nav_intranet: "Intranet", nav_contact: "Contact",
    hero_kicker: "Admissions Open — Semester 1/2026",
    hero_title_1: "Learn for the Future,", hero_title_2: "Innovate for Society",
    hero_desc: "Example University develops ethical, skilled graduates for the 21st century — connecting learning with community and industry.",
    hero_cta_primary: "Apply Online", hero_cta_ghost: "View All Programs",
    hero_card_title: "Highlights",
    breadcrumb_home: "Home",
    footer_about: "Example University",
    footer_about_desc: "Committed to quality graduates, impactful research, and academic service for sustainable development.",
    all_rights: "© 2026 Example University. All rights reserved.",
  }
};

const LANG_KEY = "eu_lang";
let currentLang = localStorage.getItem(LANG_KEY) || document.documentElement.lang || "th";
if(!["th","en"].includes(currentLang)) currentLang = "th";

function applyLang(lang){
  currentLang = lang;
  localStorage.setItem(LANG_KEY, lang);
  document.documentElement.lang = lang;
  document.querySelectorAll("[data-i18n]").forEach(el=>{
    const key = el.getAttribute("data-i18n");
    const txt = DICT[lang][key];
    if(txt) el.textContent = txt;
  });
  document.querySelectorAll("[data-i18n-placeholder]").forEach(el=>{
    const key = el.getAttribute("data-i18n-placeholder");
    const txt = DICT[lang][key];
    if(txt) el.placeholder = txt;
  });
  document.querySelectorAll("[data-footer-th]").forEach(el=>{
    const txt = lang === "en" ? el.dataset.footerEn : el.dataset.footerTh;
    if(txt) el.textContent = txt;
  });
  document.querySelectorAll(".lang-switcher button").forEach(b=> b.classList.toggle("active", b.dataset.lang === lang));
}
function initLangSwitcher(){
  document.querySelectorAll(".lang-switcher button").forEach(btn=>{
    btn.addEventListener("click", ()=> applyLang(btn.dataset.lang));
  });
}
async function initFooter(){
  const footer = document.querySelector(".site-footer");
  const footerTop = footer?.querySelector(".footer-top");
  if(!footer || !footerTop) return;

  try {
    const endpoint = new URL("admin/footer_data.php", document.baseURI);
    const response = await fetch(endpoint, {cache:"no-store"});
    if(!response.ok) throw new Error(`Footer request failed (${response.status})`);
    const data = await response.json();
    if(!Array.isArray(data.sections) || typeof data.logo_path !== "string") {
      throw new Error("Footer response has an invalid format");
    }

    const brand = footerTop.querySelector(".footer-brand");
    if(brand) {
      const logoMark = brand.querySelector('div[style*="width:42px"], div[style*="width:44px"]');
      const logo = document.createElement("img");
      logo.className = "footer-logo";
      logo.src = new URL(data.logo_path, document.baseURI).href;
      logo.alt = "University logo";
      if(logoMark) logoMark.replaceWith(logo);
      else brand.prepend(logo);

      const title = brand.querySelector("h4");
      if(title) {
        title.dataset.footerTh = DICT.th.footer_about;
        title.dataset.footerEn = DICT.en.footer_about;
      }
      const description = brand.querySelector("p");
      if(description) {
        description.dataset.footerTh = DICT.th.footer_about_desc;
        description.dataset.footerEn = DICT.en.footer_about_desc;
      }
    }

    const columns = data.sections.map(section=>{
      const column = document.createElement("div");
      column.className = "footer-col";
      const heading = document.createElement("h4");
      heading.dataset.footerTh = String(section.title_th || "");
      heading.dataset.footerEn = String(section.title_en || section.title_th || "");
      const list = document.createElement("ul");
      for(const item of (Array.isArray(section.links) ? section.links : [])) {
        const listItem = document.createElement("li");
        const link = document.createElement("a");
        const url = String(item.url || "");
        link.href = /^(https?:\/\/|mailto:|tel:|#|\/)/i.test(url)
          ? url
          : /^[a-z][a-z0-9+.-]*:/i.test(url)
            ? "#"
            : url.replace(/^(?:\.\.\/)+/, "").replace(/^\.\//, "");
        link.dataset.footerTh = String(item.label_th || "");
        link.dataset.footerEn = String(item.label_en || item.label_th || "");
        listItem.append(link);
        list.append(listItem);
      }
      column.append(heading, list);
      return column;
    });

    const footerBottom = footer.querySelector(".footer-bottom");
    footerTop.replaceChildren(...(brand ? [brand, ...columns] : columns));
    if(footerBottom) {
      const copyright = footerBottom.querySelector("span");
      if(copyright) {
        copyright.dataset.footerTh = `สงวนลิขสิทธิ์ © ${new Date().getFullYear()} มหาวิทยาลัยตัวอย่าง`;
        copyright.dataset.footerEn = `© ${new Date().getFullYear()} Example University. All rights reserved.`;
      }
    }
    applyLang(currentLang);
  } catch(error) {
    console.error("Unable to update footer from admin settings:", error);
  }
}
function initSearch(){
  const forms = document.querySelectorAll("[data-search-form]");
  forms.forEach(form=>{
    form.addEventListener("submit", (e)=>{
      e.preventDefault();
      const input = form.querySelector("input[type='search'], input[type='text']");
      const q = (input?.value || "").trim();
      if(!q){ input?.focus(); return; }
      const cards = document.querySelectorAll("[data-searchable]");
      let found = 0;
      const needle = q.toLowerCase();
      cards.forEach(c=>{
        const hay = (c.textContent || "").toLowerCase();
        const show = hay.includes(needle);
        c.style.display = show ? "" : "none";
        if(show){ found++; c.animate([{transform:'scale(1)'},{transform:'scale(1.015)'},{transform:'scale(1)'}],{duration:320, easing:'ease-out'}); }
      });
      const msg = document.getElementById("search-result-msg");
      if(msg){
        msg.hidden = false; msg.style.display="block";
        msg.style.background="rgba(0,229,255,.08)"; msg.style.border="1px solid rgba(0,229,255,.22)"; msg.style.color="#c8f5ff";
        msg.textContent = currentLang==="th" ? `ผลการค้นหา “${q}” พบ ${found} รายการ` : `Search “${q}” — ${found} result(s)`;
        msg.scrollIntoView({behavior:"smooth", block:"nearest"});
      } else alert(currentLang==="th" ? `ค้นหา: “${q}” พบ ${found} รายการ` : `Search: “${q}” — ${found} result(s)`);
      const url=new URL(window.location.href); url.searchParams.set("q",q); history.replaceState(null,"",url);
    });
  });
  const q=new URLSearchParams(window.location.search).get("q");
  if(q){ document.querySelectorAll("[data-search-form] input").forEach(i=> i.value=q); document.querySelector("[data-search-form]")?.dispatchEvent(new Event("submit",{cancelable:true})); }
}
function initNav(){
  document.querySelectorAll(".has-dropdown > .nav-toggle").forEach(btn=>{
    btn.addEventListener("click", (e)=>{
      const li=btn.closest(".has-dropdown"); const open=li.classList.contains("open");
      document.querySelectorAll(".has-dropdown.open").forEach(o=> o.classList.remove("open"));
      if(!open) li.classList.add("open"); e.stopPropagation();
    });
  });
  document.addEventListener("click", ()=> document.querySelectorAll(".has-dropdown.open").forEach(o=> o.classList.remove("open")));
  const ham=document.getElementById("hamburger"), panel=document.getElementById("mobile-panel");
  ham?.addEventListener("click", ()=>{ const open=panel.classList.toggle("open"); ham.setAttribute("aria-expanded",String(open)); });
  document.querySelectorAll("[data-mobile-toggle]").forEach(btn=>{
    btn.addEventListener("click", ()=>{
      const id=btn.getAttribute("data-mobile-toggle");
      document.getElementById(id)?.classList.toggle("open");
      btn.setAttribute("aria-expanded", String(btn.getAttribute("aria-expanded")!=="true"));
    });
  });
}
function injectFutureFX(){
  if(!document.querySelector(".scanline")){ const s=document.createElement("div"); s.className="scanline"; s.setAttribute("aria-hidden","true"); document.body.appendChild(s); }
  document.querySelectorAll(".hero").forEach(hero=>{
    if(hero.querySelector(".hero-orb")) return;
    ["hero-orb--1","hero-orb--2","hero-orb--3"].forEach(cls=>{
      const d=document.createElement("div"); d.className="hero-orb "+cls; d.setAttribute("aria-hidden","true"); hero.prepend(d);
    });
  });
}
function initReveal(){
  document.querySelectorAll(".section, .card, .app-tile, .stat, .hero-card, .sitemap-col").forEach((el,i)=>{
    el.classList.add("reveal"); el.style.transitionDelay=(i%6)*40+"ms";
  });
  const io=new IntersectionObserver((ents)=>{ ents.forEach(en=>{ if(en.isIntersecting){ en.target.classList.add("in"); io.unobserve(en.target); } }); },{threshold:.12, rootMargin:"0px 0px -40px 0px"});
  document.querySelectorAll(".reveal").forEach(el=> io.observe(el));
}
const personnelImageSources=new WeakMap();
function initPersonnelModal(){
  const modal=document.querySelector("[data-personnel-modal]");
  const gallery=document.querySelector("[data-personnel-gallery]");
  if(!modal || !gallery) return;
  const images=modal.querySelector("[data-personnel-modal-images]");
  const name=modal.querySelector("[data-personnel-modal-name]");
  const faculty=modal.querySelector("[data-personnel-modal-faculty]");
  let lastCard=null;
  const close=()=>{
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden","true");
    document.body.classList.remove("modal-open");
    lastCard?.focus();
  };
  const open=(card)=>{
    const cardImages=personnelImageSources.get(card) || [];
    const caption=card.querySelector("figcaption");
    lastCard=card;
    modal.querySelector(".personnel-modal-dialog").style.gridTemplateColumns=cardImages.length ? "" : "1fr";
    images.replaceChildren(...cardImages.map((source,index)=>{
      const image=document.createElement("img");
      image.src=source;
      image.alt=`รูปที่ ${index+1}`;
      image.loading="lazy";
      return image;
    }));
    name.textContent=caption.querySelector("strong")?.textContent || "บุคลากร";
    faculty.textContent=caption.querySelector("span")?.textContent || "";
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden","false");
    document.body.classList.add("modal-open");
    modal.querySelector(".personnel-modal-close")?.focus();
  };
  gallery.addEventListener("click", event=>{
    const card=event.target.closest("[data-personnel-card]");
    if(card) open(card);
  });
  gallery.addEventListener("keydown", event=>{
    const card=event.target.closest("[data-personnel-card]");
    if(card && (event.key==="Enter" || event.key===" ")){ event.preventDefault(); open(card); }
  });
  modal.querySelectorAll("[data-personnel-close]").forEach(el=>el.addEventListener("click",close));
  document.addEventListener("keydown", e=>{ if(e.key==="Escape" && modal.classList.contains("is-open")) close(); });
}
async function initPersonnelData(){
  const gallery=document.querySelector("[data-personnel-gallery]");
  if(!gallery) return;

  const showMessage=(text)=>{
    const message=document.createElement("p");
    message.className="personnel-gallery-message";
    message.textContent=text;
    gallery.replaceChildren(message);
  };

  const controller=new AbortController();
  const timeoutId=window.setTimeout(()=>controller.abort(),10000);

  try{
    const response=await fetch("personnel_data.php",{
      headers:{Accept:"application/json"},
      cache:"no-store",
      signal:controller.signal
    });
    if(!response.ok) throw new Error("Personnel request failed");
    const data=await response.json();
    if(!Array.isArray(data.items)) throw new Error("Invalid personnel response");
    if(!data.items.length){ showMessage("ยังไม่มีข้อมูลบุคลากร"); return; }

    const cards=data.items.map(item=>{
      const fullName=[item.prefix,item.fullname].filter(Boolean).join(" ").trim();
      const imagePaths=Array.isArray(item.images) ? item.images.filter(path=>typeof path==="string" && path) : (item.image ? [item.image] : []);
      const card=document.createElement("figure");
      card.className="personnel-photo";
      card.dataset.personnelCard="";
      card.dataset.searchable="";
      card.tabIndex=0;
      card.setAttribute("role","button");
      card.setAttribute("aria-label",`ดูข้อมูล ${fullName}`);
      personnelImageSources.set(card,imagePaths);

      if(imagePaths.length){
        const preview=document.createElement("div");
        preview.className="personnel-photo-preview";
        const image=document.createElement("img");
        image.src=imagePaths[0];
        image.alt=`รูปของ ${fullName}`;
        image.loading="lazy";
        preview.append(image);
        if(imagePaths.length>1){
          const count=document.createElement("span");
          count.className="personnel-photo-count";
          count.textContent=`${imagePaths.length} รูป`;
          preview.append(count);
        }
        card.append(preview);
      }else{
        const placeholder=document.createElement("div");
        placeholder.className="personnel-photo-placeholder";
        placeholder.textContent=fullName.charAt(0) || "บ";
        placeholder.setAttribute("aria-hidden","true");
        card.append(placeholder);
      }

      const caption=document.createElement("figcaption");
      const name=document.createElement("strong");
      name.textContent=fullName || "บุคลากร";
      const details=document.createElement("span");
      details.textContent=[item.position,item.department].filter(Boolean).join(" · ");
      caption.append(name,details);
      card.append(caption);
      return card;
    });

    gallery.replaceChildren(...cards);
    const currentQuery=new URLSearchParams(window.location.search).get("q");
    if(currentQuery){
      const searchForm=document.querySelector("[data-search-form]");
      const searchInput=searchForm?.querySelector("input[type='search'], input[type='text']");
      if(searchForm && searchInput){
        searchInput.value=currentQuery;
        searchForm.dispatchEvent(new Event("submit",{cancelable:true}));
      }
    }
  }catch(error){
    showMessage("ไม่สามารถโหลดข้อมูลบุคลากรได้ กรุณาลองใหม่ภายหลัง");
  }finally{
    window.clearTimeout(timeoutId);
  }
}

async function initCourseData(){
  const courseLists={
    bachelor:document.querySelector('[data-course-list="bachelor"]'),
    graduate:document.querySelector('[data-course-list="graduate"]'),
    short:document.querySelector('[data-course-list="short"]'),
    other:document.querySelector('[data-course-list="other"]')
  };
  const facultyList=document.querySelector("[data-faculty-list]");
  const homeCourseList=document.querySelector("[data-home-course-list]");
  const homeFacultyList=document.querySelector("[data-home-faculty-list]");
  if(!facultyList && !homeCourseList && !homeFacultyList && !Object.values(courseLists).some(Boolean)) return;

  const showMessage=(container,text)=>{
    if(!container) return;
    const message=document.createElement("p");
    message.className="muted";
    message.textContent=text;
    container.replaceChildren(message);
  };

  const controller=new AbortController();
  const timeoutId=window.setTimeout(()=>controller.abort(),10000);

  try{
    const response=await fetch("courses_data.php",{
      headers:{Accept:"application/json"},
      cache:"no-store",
      signal:controller.signal
    });
    if(!response.ok) throw new Error("Courses request failed");
    const data=await response.json();
    if(!Array.isArray(data.items)) throw new Error("Invalid courses response");

    const groups={bachelor:[],graduate:[],short:[],other:[]};
    const faculties=new Map();
    data.items.forEach(item=>{
      const degree=String(item.degree || "").trim().toLowerCase();
      let group="other";
      if(degree.includes("ปริญญาตรี") || /bachelor|undergraduate/.test(degree)) group="bachelor";
      else if(/ปริญญาโท|ปริญญาเอก|บัณฑิตศึกษา|master|doctor|graduate|ph\.?d/.test(degree)) group="graduate";
      else if(/ระยะสั้น|ประกาศนียบัตร|อบรม|short|certificate|micro/.test(degree)) group="short";
      groups[group].push(item);

      const faculty=String(item.faculty || "").trim();
      if(faculty) faculties.set(faculty,(faculties.get(faculty) || 0)+1);
    });

    const count=document.querySelector("[data-course-count]");
    if(count) count.textContent=String(data.items.length);
    const facultyCount=document.querySelector("[data-faculty-count]");
    if(facultyCount) facultyCount.textContent=String(faculties.size);

    const createCourseCard=item=>{
      const card=document.createElement("article");
      card.className="card";
      card.dataset.searchable="";

      const title=document.createElement("h3");
      title.textContent=item.course_name || "หลักสูตร";
      card.append(title);

      const details=[
        item.course_code ? `รหัสหลักสูตร: ${item.course_code}` : "",
        item.degree || "",
        item.faculty ? `คณะ / วิทยาลัย: ${item.faculty}` : "",
        item.department ? `สาขา: ${item.department}` : "",
        item.credits !== null && item.credits !== "" ? `หน่วยกิต ${item.credits}` : ""
      ].filter(Boolean);
      if(details.length){
        const metadata=document.createElement("p");
        metadata.textContent=details.join(" · ");
        card.append(metadata);
      }

      if(item.description){
        const description=document.createElement("p");
        description.textContent=item.description;
        card.append(description);
      }
      return card;
    };

    const createFacultyCard=([name,courseCount])=>{
      const card=document.createElement("article");
      card.className="card";
      card.dataset.searchable="";
      const title=document.createElement("h3");
      title.textContent=name;
      const details=document.createElement("p");
      details.textContent=`${courseCount} หลักสูตร`;
      card.append(title,details);
      return card;
    };

    Object.entries(courseLists).forEach(([group,container])=>{
      if(!container) return;
      const items=groups[group];
      if(!items.length){
        showMessage(container,group==="other" ? "ไม่มีหลักสูตรในหมวดนี้" : "ยังไม่มีหลักสูตรในหมวดนี้");
        return;
      }

      container.replaceChildren(...items.map(createCourseCard));
    });

    if(homeCourseList){
      if(data.items.length){
        homeCourseList.replaceChildren(...data.items.slice(0,3).map(createCourseCard));
      }else{
        showMessage(homeCourseList,"ยังไม่มีหลักสูตรที่เปิดสอน");
      }
    }

    if(facultyList){
      if(!faculties.size){
        showMessage(facultyList,"ยังไม่มีข้อมูลคณะ/วิทยาลัย กรุณาระบุในระบบแอดมิน");
      }else{
        facultyList.replaceChildren(...Array.from(faculties,createFacultyCard));
      }
    }

    if(homeFacultyList){
      if(!faculties.size){
        showMessage(homeFacultyList,"ยังไม่มีข้อมูลคณะ/วิทยาลัย");
      }else{
        homeFacultyList.replaceChildren(...Array.from(faculties,createFacultyCard).slice(0,4));
      }
    }
  }catch(error){
    const message="ไม่สามารถโหลดข้อมูลหลักสูตรได้ กรุณาลองใหม่ภายหลัง";
    Object.values(courseLists).forEach(container=>showMessage(container,message));
    showMessage(facultyList,message);
    showMessage(homeCourseList,message);
    showMessage(homeFacultyList,message);
  }finally{
    window.clearTimeout(timeoutId);
  }
}

async function initNewsData(){
  const newsLists={
    university:document.querySelector('[data-news-list="university"]'),
    faculty:document.querySelector('[data-news-list="faculty"]'),
    event:document.querySelector('[data-news-list="event"]'),
    announcement:document.querySelector('[data-news-list="announcement"]')
  };
  const homeNewsList=document.querySelector("[data-home-news-list]");
  const featuredList=document.querySelector("[data-home-featured-news]");
  if(!homeNewsList && !featuredList && !Object.values(newsLists).some(Boolean)) return;

  const categoryLabels={
    university:"ข่าวมหาวิทยาลัย",
    faculty:"ข่าวคณะ",
    event:"กิจกรรม",
    announcement:"ประกาศ"
  };
  const showCardMessage=(container,text)=>{
    if(!container) return;
    const message=document.createElement("p");
    message.className="muted";
    message.textContent=text;
    container.replaceChildren(message);
  };
  const showFeaturedMessage=text=>{
    if(!featuredList) return;
    const item=document.createElement("li");
    item.textContent=text;
    featuredList.replaceChildren(item);
  };

  const controller=new AbortController();
  const timeoutId=window.setTimeout(()=>controller.abort(),10000);

  try{
    const response=await fetch("news_data.php",{
      headers:{Accept:"application/json"},
      cache:"no-store",
      signal:controller.signal
    });
    if(!response.ok) throw new Error("News request failed");
    const data=await response.json();
    if(!Array.isArray(data.items)) throw new Error("Invalid news response");

    const groups={university:[],faculty:[],event:[],announcement:[]};
    const normalizeType=type=>type==="news" ? "university" : type;
    data.items.forEach(item=>{
      const type=normalizeType(item.content_type);
      if(groups[type]) groups[type].push(item);
    });

    const createNewsCard=item=>{
      const type=normalizeType(item.content_type);
      const card=document.createElement("article");
      card.className="card";
      card.dataset.searchable="";

      const label=document.createElement("span");
      label.className="badge";
      label.textContent=categoryLabels[type] || "ข่าวสาร";
      const title=document.createElement("h3");
      title.textContent=item.title || "ข่าวสาร";
      const summary=document.createElement("p");
      summary.textContent=item.summary || "";
      card.append(label,title,summary);
      const imagePaths=Array.isArray(item.images) ? item.images.filter(path=>typeof path==="string" && path) : [];
      if(imagePaths.length){
        const imageGallery=document.createElement("div");
        imageGallery.className="news-card-images";
        imagePaths.forEach((path,index)=>{
          const image=document.createElement("img");
          image.src=path;
          image.alt=`${item.title || "ข่าวสาร"} รูปที่ ${index+1}`;
          image.loading="lazy";
          imageGallery.append(image);
        });
        card.append(imageGallery);
      }

      const rawDate=type==="event" && item.event_date ? item.event_date : item.created_at;
      if(rawDate){
        const date=document.createElement("p");
        date.className="muted";
        date.textContent=new Date(`${rawDate.slice(0,10)}T00:00:00`).toLocaleDateString(currentLang==="th" ? "th-TH" : "en-GB");
        card.append(date);
      }

      if(item.body){
        const details=document.createElement("details");
        const more=document.createElement("summary");
        more.textContent=currentLang==="th" ? "อ่านรายละเอียด" : "Read details";
        const body=document.createElement("p");
        body.textContent=item.body;
        body.style.whiteSpace="pre-wrap";
        details.append(more,body);
        card.append(details);
      }
      return card;
    };

    Object.entries(newsLists).forEach(([type,container])=>{
      if(!container) return;
      const items=groups[type];
      if(items.length){
        container.replaceChildren(...items.map(createNewsCard));
      }else{
        showCardMessage(container,`ยังไม่มี${categoryLabels[type]}ที่เผยแพร่`);
      }
    });

    if(homeNewsList){
      if(data.items.length){
        homeNewsList.replaceChildren(...data.items.slice(0,3).map(createNewsCard));
      }else{
        showCardMessage(homeNewsList,"ยังไม่มีข่าวที่เผยแพร่");
      }
    }

    if(featuredList){
      const featured=data.items.slice(0,3).map(item=>{
        const row=document.createElement("li");
        row.dataset.searchable="";
        const dot=document.createElement("span");
        dot.className="dot";
        const content=document.createElement("span");
        const title=document.createElement("strong");
        title.textContent=item.title || "ข่าวสาร";
        const metadata=document.createElement("small");
        metadata.textContent=`${categoryLabels[normalizeType(item.content_type)] || "ข่าวสาร"} · ${item.summary || ""}`;
        content.append(title,document.createElement("br"),metadata);
        row.append(dot,content);
        return row;
      });
      if(featured.length) featuredList.replaceChildren(...featured);
      else showFeaturedMessage("ยังไม่มีข่าวที่เผยแพร่");
    }
  }catch(error){
    const message="ไม่สามารถโหลดข่าวสารได้ กรุณาลองใหม่ภายหลัง";
    Object.values(newsLists).forEach(container=>showCardMessage(container,message));
    showCardMessage(homeNewsList,message);
    showFeaturedMessage(message);
  }finally{
    window.clearTimeout(timeoutId);
  }
}

/* ================= SPACE ================= */
function initSpace(){
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  // canvas starfield
  let canvas=document.getElementById("space-canvas");
  if(!canvas){
    canvas=document.createElement("canvas"); canvas.id="space-canvas";
    document.body.prepend(canvas);
    const vig=document.createElement("div"); vig.className="space-vignette"; vig.setAttribute("aria-hidden","true");
    document.body.insertBefore(vig, canvas.nextSibling);
    const warp=document.createElement("div"); warp.className="warp-lines"; warp.setAttribute("aria-hidden","true");
    document.body.appendChild(warp);
  }
  const ctx=canvas.getContext("2d");
  let stars=[], rafId, mouseX=0, mouseY=0, scrollY=0, w=0, h=0, dpr=1;
  function resize(){
    dpr=Math.min(window.devicePixelRatio||1, 2);
    w=canvas.width=Math.floor(window.innerWidth*dpr);
    h=canvas.height=Math.floor(window.innerHeight*dpr);
    canvas.style.width=window.innerWidth+"px";
    canvas.style.height=window.innerHeight+"px";
    ctx.setTransform(dpr,0,0,dpr,0,0);
    buildStars();
  }
  function buildStars(){
    stars=[];
    const W=window.innerWidth, H=window.innerHeight;
    const count = W<760 ? 110 : 220;
    for(let i=0;i<count;i++){
      const layer = i< count*0.55 ? 0 : i< count*0.85 ? 1 : 2;
      stars.push({
        x: Math.random()*W,
        y: Math.random()*H,
        r: layer===0 ? Math.random()*0.9+0.25 : layer===1 ? Math.random()*1.15+0.5 : Math.random()*1.6+0.8,
        base: layer===0 ? 0.32+Math.random()*0.28 : layer===1 ? 0.5+Math.random()*0.35 : 0.65+Math.random()*0.35,
        tw: 0.6+Math.random()*1.8,
        ph: Math.random()*Math.PI*2,
        layer,
        hue: Math.random()<0.12 ? (Math.random()<0.5? 190: 265) : 0, // occasional cyan/violet
        drift: (Math.random()-0.5)*0.18
      });
    }
  }
  let t0=performance.now();
  function draw(){
    const W=window.innerWidth, H=window.innerHeight;
    const now=performance.now(), t=(now-t0)/1000;
    ctx.clearRect(0,0,W,H);
    // subtle nebula wash already via CSS, stars are sharp
    for(const s of stars){
      const parallaxX = (mouseX* (s.layer+1)*6) + (scrollY*0.02*(s.layer+1));
      const parallaxY = (mouseY* (s.layer+1)*4) - (scrollY*0.015*(s.layer+1));
      // drift
      const x = ((s.x + parallaxX + s.drift*t*8) % W + W) % W;
      const y = ((s.y + parallaxY) % H + H) % H;
      const tw = 0.5 + 0.5*Math.sin(t*s.tw + s.ph);
      const a = s.base * (0.55 + 0.45*tw);
      if(a<0.04) continue;
      // star core
      ctx.beginPath();
      if(s.layer===2 && a>0.6){
        // glow for bright stars
        const g=ctx.createRadialGradient(x,y,0,x,y,s.r*4.2);
        const col = s.hue ? `hsla(${s.hue},100%,68%,` : `hsla(195,100%,85%,`;
        g.addColorStop(0, col+a+`)`);
        g.addColorStop(0.35, col+(a*0.35)+`)`);
        g.addColorStop(1, `hsla(0,0%,100%,0)`);
        ctx.fillStyle=g;
        ctx.arc(x,y,s.r*4.2,0,Math.PI*2); ctx.fill();
        ctx.beginPath();
      }
      ctx.fillStyle = s.hue ? `hsla(${s.hue},100%,75%,${a})` : `rgba(255,255,255,${a})`;
      // cross sparkle for brightest
      if(s.layer===2 && s.r>1.2 && tw>0.82){
        const l=s.r*2.6;
        ctx.fillRect(x-l/2,y-0.45,l,0.9);
        ctx.fillRect(x-0.45,y-l/2,0.9,l);
        ctx.arc(x,y,s.r,0,Math.PI*2); ctx.fill();
      } else {
        ctx.arc(x,y,s.r,0,Math.PI*2); ctx.fill();
      }
    }
    rafId=requestAnimationFrame(draw);
  }
  window.addEventListener("resize", resize);
  window.addEventListener("mousemove", (e)=>{ mouseX=(e.clientX/window.innerWidth-0.5); mouseY=(e.clientY/window.innerHeight-0.5); });
  window.addEventListener("scroll", ()=>{ scrollY=window.scrollY; }, {passive:true});
  resize();
  if(!reduce) draw(); else {
    // static render once
    const W=window.innerWidth, H=window.innerHeight;
    ctx.clearRect(0,0,W,H);
    for(const s of stars){ ctx.fillStyle=`rgba(255,255,255,${s.base*0.85})`; ctx.beginPath(); ctx.arc(s.x,s.y,s.r,0,Math.PI*2); ctx.fill(); }
  }

  // shooting stars
  function shootOnce(x0,y0){
    const el=document.createElement("div"); el.className="shooting-star";
    const startX = x0 ?? (Math.random()*window.innerWidth*0.7);
    const startY = y0 ?? (Math.random()*window.innerHeight*0.45 - 40);
    const len = 120 + Math.random()*80;
    const ang = 22 + Math.random()*14; // deg
    el.style.left=startX+"px"; el.style.top=startY+"px";
    el.style.width=len+"px";
    el.style.transform=`rotate(${ang}deg)`;
    el.style.transformOrigin="left center";
    document.body.appendChild(el);
    const dist = 420 + Math.random()*520;
    const dx = Math.cos(ang*Math.PI/180)*dist, dy=Math.sin(ang*Math.PI/180)*dist;
    const anim=el.animate([
      {opacity:0, transform:`rotate(${ang}deg) translateX(0)`},
      {opacity:1, offset:0.08},
      {opacity:1, offset:0.82},
      {opacity:0, transform:`rotate(${ang}deg) translateX(${dist}px)`}
    ],{duration: 900+Math.random()*700, easing:"cubic-bezier(.2,.6,.2,1)"});
    anim.onfinish=()=> el.remove();
    // trail fade
    el.animate([{opacity:1},{opacity:0}],{duration: anim.effect.getTiming().duration, easing:"ease-in"});
  }
  let shootTimer;
  function autoShoot(){
    if(document.hidden) return;
    if(Math.random()<0.24) shootOnce();
    shootTimer=setTimeout(autoShoot, 3200 + Math.random()*5200);
  }
  if(!reduce) shootTimer=setTimeout(autoShoot, 1200);

  // planets
  const sceneId="space-scene";
  let scene=document.getElementById(sceneId);
  if(!scene){
    scene=document.createElement("div");
    scene.id=sceneId;
    scene.setAttribute("aria-hidden","false");
    scene.style.cssText="position:fixed;inset:0;pointer-events:none;z-index:1;overflow:hidden";
    document.body.appendChild(scene);
  }
  const planetsData=[
    {name:"วิศวกรรม", en:"Engineering", href:"programs.php", color:"linear-gradient(135deg,#00e5ff,#2563eb)", ring:true, x:"7%", y:"16%", s:64},
    {name:"บริหารธุรกิจ", en:"Business", href:"programs.php", color:"linear-gradient(135deg,#ff7ac0,#ff2d95)", ring:true, x:"80%", y:"72%", s:58},
  ];
  planetsData.forEach((pl,i)=>{
    if(scene.querySelector(`[data-planet="${pl.name}"]`)) return;
    const el=document.createElement("button");
    el.dataset.planet=pl.name;
    el.className="planet"+(pl.ring?" planet--ring":"");
    el.style.left=pl.x; el.style.top=pl.y;
    el.style.width=pl.s+"px"; el.style.height=pl.s+"px";
    el.style.background=pl.color;
    el.style.pointerEvents="auto";
    el.style.boxShadow=`inset -10px -12px 18px rgba(0,0,0,.35), inset 4px 4px 10px rgba(255,255,255,.22), 0 0 22px rgba(0,229,255,.18)`;
    el.style.animation=`planetFloat${i} ${6+i*1.2}s ease-in-out infinite`;
    el.setAttribute("aria-label", pl.name+" — คลิกเพื่อดูหลักสูตร");
    el.title=pl.name+" / "+pl.en;
    const label=document.createElement("span"); label.className="planet-label"; label.textContent=pl.name;
    el.appendChild(label);
    // crater dots
    for(let c=0;c<3;c++){
      const dot=document.createElement("span");
      dot.style.cssText=`position:absolute;border-radius:50%;background:rgba(0,0,0,.18);pointer-events:none;`;
      const sz= pl.s*(0.14+Math.random()*0.1);
      dot.style.width=sz+"px"; dot.style.height=sz+"px";
      dot.style.left=(18+Math.random()*42)+"%"; dot.style.top=(22+Math.random()*38)+"%";
      el.appendChild(dot);
    }
    el.addEventListener("click", ()=>{
      starBurst(window.innerWidth*0.5, window.innerHeight*0.42, 18, pl.color);
      shootOnce(window.innerWidth*0.5-60, 60);
      setTimeout(()=> window.location.href=pl.href, 420);
    });
    scene.appendChild(el);
    // keyframes per planet
    const style=document.createElement("style");
    style.textContent=`@keyframes planetFloat${i}{0%,100%{transform:translateY(0)}50%{transform:translateY(-${6+i*2}px)}}`;
    document.head.appendChild(style);
  });
  // parallax planets on mouse
  scene.addEventListener("mousemove", (e)=>{
    const x=(e.clientX/window.innerWidth-0.5), y=(e.clientY/window.innerHeight-0.5);
    scene.querySelectorAll(".planet").forEach((p,i)=>{
      p.style.transform=`translate(${x*10*(i%2? -1:1)}px, ${y*8*(i%2?1:-1)}px)`;
    });
  });

  // astronaut (draggable)
  let astro=document.getElementById("astro");
  if(!astro){
    astro=document.createElement("div");
    astro.id="astro"; astro.className="astro";
    astro.style.left="52%"; astro.style.top="54%";
    astro.innerHTML=`🧑‍🚀<small>ลากฉันได้!</small>`;
    astro.setAttribute("aria-label","นักบินอวกาศ — ลากได้ คลิกเพื่อทักทาย");
    astro.style.pointerEvents="auto";
    scene.appendChild(astro);
    let dragging=false, sx=0, sy=0, ox=0, oy=0;
    astro.addEventListener("pointerdown", (e)=>{
      dragging=true; astro.setPointerCapture(e.pointerId);
      sx=e.clientX; sy=e.clientY;
      const r=astro.getBoundingClientRect(); ox=r.left; oy=r.top;
      astro.style.animation="none"; astro.style.cursor="grabbing";
    });
    astro.addEventListener("pointermove", (e)=>{
      if(!dragging) return;
      const dx=e.clientX-sx, dy=e.clientY-sy;
      astro.style.left=(ox+dx)+"px"; astro.style.top=(oy+dy)+"px";
      astro.style.right="auto"; astro.style.bottom="auto";
      astro.style.position="fixed";
    });
    astro.addEventListener("pointerup", (e)=>{
      dragging=false; astro.style.cursor="grab";
      // snap back animation resume after 1s
      setTimeout(()=> astro.style.animation="astroFloat 4s ease-in-out infinite", 900);
      // burst
      const r=astro.getBoundingClientRect();
      starBurst(r.left+r.width/2, r.top+r.height/2, 14);
    });
    astro.addEventListener("click", ()=>{
      const r=astro.getBoundingClientRect();
      starBurst(r.left+r.width/2, r.top+r.height/2, 16);
      shootOnce(r.left, r.top);
      astro.animate([{transform:"scale(1)"},{transform:"scale(1.15) rotate(6deg)"},{transform:"scale(1)"}],{duration:420, easing:"ease-out"});
    });
  }

  // UFO flyby
  function ufoFly(){
    if(document.hidden) return;
    const ufo=document.createElement("div");
    ufo.textContent="🛸";
    ufo.style.cssText=`position:fixed;left:-60px;top:${12+Math.random()*28}%;font-size:1.8rem;z-index:3;pointer-events:none;filter:drop-shadow(0 0 12px rgba(0,229,255,.6));`;
    document.body.appendChild(ufo);
    const endX=window.innerWidth+80, dur= 4200+Math.random()*2800;
    ufo.animate([{transform:`translateX(0) translateY(0)`},{transform:`translateX(${endX}px) translateY(${Math.sin(Date.now())*10}px)`}],{duration:dur, easing:"linear"}).onfinish=()=> ufo.remove();
    // beam occasionally
    setTimeout(()=>{
      const beam=document.createElement("div");
      beam.style.cssText=`position:fixed;left:50%;top:18%;width:120px;height:180px;background:linear-gradient(180deg, rgba(0,229,255,.28), transparent);clip-path:polygon(20% 0,80% 0,100% 100%,0 100%);pointer-events:none;z-index:2;opacity:.0;transform:translateX(-50%);`;
      document.body.appendChild(beam);
      beam.animate([{opacity:0},{opacity:1, offset:0.2},{opacity:0}],{duration:900}).onfinish=()=> beam.remove();
    }, dur*0.45);
  }
  if(!reduce) setInterval(()=>{ if(Math.random()<0.12) ufoFly(); }, 12000);

  // comet cursor trail
  let cometOn=false, cometDots=[];
  function onMouseMoveComet(e){
    if(!cometOn) return;
    if(cometDots.length>14) return;
    const d=document.createElement("div"); d.className="comet-dot";
    d.style.left=e.clientX+"px"; d.style.top=e.clientY+"px";
    d.style.opacity="0.9";
    document.body.appendChild(d);
    cometDots.push(d);
    d.animate([{opacity:0.9, transform:"translate(-50%,-50%) scale(1)"},{opacity:0, transform:"translate(-50%,-50%) scale(0.2)"}],{duration:520, easing:"ease-out"}).onfinish=()=>{ d.remove(); cometDots.splice(cometDots.indexOf(d),1); };
  }
  window.addEventListener("mousemove", onMouseMoveComet, {passive:true});

  // star burst on click (anywhere, but not on buttons)
  function starBurst(cx,cy,count=16, color){
    const wrap=document.createElement("div"); wrap.className="star-burst";
    wrap.style.left=cx+"px"; wrap.style.top=cy+"px";
    document.body.appendChild(wrap);
    for(let i=0;i<count;i++){
      const p=document.createElement("i");
      const ang=(i/count)*Math.PI*2 + Math.random()*0.3;
      const dist= 42+Math.random()*64;
      const dx=Math.cos(ang)*dist, dy=Math.sin(ang)*dist;
      p.style.left="0"; p.style.top="0";
      if(color) p.style.background=color.includes("gradient")?"#fff":color;
      wrap.appendChild(p);
      p.animate([
        {transform:`translate(0,0) scale(1)`, opacity:1},
        {transform:`translate(${dx}px, ${dy}px) scale(0)`, opacity:0}
      ],{duration: 520+Math.random()*260, easing:"cubic-bezier(.2,.8,.2,1)", delay: Math.random()*60}).onfinish=()=>{
        if(i===count-1) wrap.remove();
      };
    }
    // central flash
    const flash=document.createElement("div");
    flash.style.cssText=`position:fixed;left:${cx}px;top:${cy}px;width:18px;height:18px;margin:-9px 0 0 -9px;border-radius:50%;background:radial-gradient(circle, #fff, rgba(0,229,255,.55) 45%, transparent 72%);pointer-events:none;z-index:31;`;
    document.body.appendChild(flash);
    flash.animate([{transform:"scale(0.2)", opacity:1},{transform:"scale(1.8)", opacity:0}],{duration:380, easing:"ease-out"}).onfinish=()=> flash.remove();
  }
  document.addEventListener("click", (e)=>{
    // ignore clicks on interactive elements
    if(e.target.closest("a, button, input, select, textarea, .planet, .astro, .space-dock")) return;
    if(e.target.closest(".hero, .page-hero, .section, .site-footer")){
      starBurst(e.clientX, e.clientY, 12);
      if(Math.random()<0.28) shootOnce(e.clientX-40, e.clientY-40);
    }
  });

  // warp drive
  function warp(){
    document.body.classList.add("warp");
    for(let i=0;i<6;i++) setTimeout(()=> shootOnce(Math.random()*window.innerWidth, Math.random()*80), i*90);
    starBurst(window.innerWidth/2, window.innerHeight/2, 22);
    setTimeout(()=> document.body.classList.remove("warp"), 1400);
  }

  // constellation (connect planets + random stars)
  let constOn=false, svg=document.getElementById("constellation-svg");
  if(!svg){
    svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
    svg.id="constellation-svg"; svg.setAttribute("aria-hidden","true");
    svg.style.cssText="position:fixed;inset:0;z-index:2;pointer-events:none;";
    document.body.appendChild(svg);
  }
  function drawConstellation(){
    svg.innerHTML="";
    const pts=[];
    scene.querySelectorAll(".planet").forEach(p=>{
      const r=p.getBoundingClientRect();
      pts.push({x:r.left+r.width/2, y:r.top+r.height/2});
    });
    // add 2 random star points
    for(let i=0;i<2;i++) pts.push({x: Math.random()*window.innerWidth, y: Math.random()*window.innerHeight*0.6+40});
    for(let i=0;i<pts.length-1;i++){
      const a=pts[i], b=pts[i+1];
      const line=document.createElementNS("http://www.w3.org/2000/svg","line");
      line.setAttribute("x1",a.x); line.setAttribute("y1",a.y);
      line.setAttribute("x2",b.x); line.setAttribute("y2",b.y);
      line.setAttribute("class","const-line");
      svg.appendChild(line);
      // animate dash
      let off=0;
      const anim=()=>{ off=(off+1)%24; line.style.strokeDashoffset=off; if(constOn) requestAnimationFrame(anim); };
      if(!reduce) requestAnimationFrame(anim);
    }
    pts.forEach((p,i)=>{
      const c=document.createElementNS("http://www.w3.org/2000/svg","circle");
      c.setAttribute("cx",p.x); c.setAttribute("cy",p.y); c.setAttribute("r", i<4? "4":"2.5");
      c.setAttribute("class","const-star"+(i<1?" active":""));
      svg.appendChild(c);
    });
  }

  // space dock controls — inject into hero or top
  let dock=document.getElementById("space-dock");
  if(!dock){
    dock=document.createElement("div");
    dock.id="space-dock"; dock.className="space-dock";
    dock.setAttribute("role","toolbar"); dock.setAttribute("aria-label","ลูกเล่นอวกาศ");
    dock.innerHTML=`
      <button type="button" data-act="shoot" title="ยิงดาวตก">✨ ดาวตก</button>
      <button type="button" data-act="warp" title="วาร์ป!">🚀 วาร์ป</button>
      <button type="button" data-act="comet" title="หางดาวตามเมาส์">☄️ หางดาว</button>
      <button type="button" data-act="const" title="กลุ่มดาว">🌌 กลุ่มดาว</button>
      <button type="button" data-act="astro" title="ซ่อน/โชว์นักบิน">🧑‍🚀 นักบิน</button>
      <button type="button" data-act="ufo" title="เรียก UFO">🛸 UFO</button>
    `;
    // place after hero h2 or at bottom-right fixed on scroll
    const heroInner=document.querySelector(".hero-inner");
    if(heroInner){
      const wrap=document.createElement("div");
      wrap.style.cssText="margin-top:1rem;display:flex;justify-content:flex-start";
      wrap.appendChild(dock);
      heroInner.firstElementChild?.appendChild(wrap);
    } else {
      dock.style.cssText+=";position:fixed;right:12px;bottom:12px;z-index:30";
      document.body.appendChild(dock);
    }
    // also add floating dock for mobile
    const floatDock=dock.cloneNode(true);
    floatDock.id="space-dock-float";
    floatDock.style.cssText="position:fixed;right:10px;bottom:10px;z-index:30;max-width:92vw;overflow:auto;scrollbar-width:none";
    // only use one dock - move original to fixed on small screens via CSS is ok, keep hero dock
    // so add float only if hero not found
    if(!heroInner) dock=floatDock;
    else {
      // keep hero dock, also add a mini fixed dock for convenience
      floatDock.style.cssText="position:fixed;right:10px;bottom:10px;z-index:30;display:flex;gap:.4rem;background:rgba(10,16,38,.82);border:1px solid rgba(255,255,255,.1);padding:.4rem .5rem;border-radius:999px;backdrop-filter:blur(12px);box-shadow:0 8px 24px rgba(0,0,0,.32)";
      floatDock.querySelectorAll("button").forEach(b=> b.style.fontSize=".72rem");
      document.body.appendChild(floatDock);
      // sync active states
      floatDock.querySelectorAll("button").forEach((b,i)=>{
        b.addEventListener("click", ()=> dock.querySelectorAll("button")[i]?.click());
      });
    }
  }
  // dock events
  const mainDock=document.getElementById("space-dock");
  if(mainDock){
    mainDock.addEventListener("click", (e)=>{
      const btn=e.target.closest("button[data-act]"); if(!btn) return;
      const act=btn.dataset.act;
      if(act==="shoot"){ shootOnce(); starBurst(window.innerWidth*0.5, 120, 10); }
      else if(act==="warp"){ warp(); }
      else if(act==="comet"){ cometOn=!cometOn; btn.classList.toggle("active", cometOn); document.getElementById("space-dock-float")?.querySelector('[data-act="comet"]')?.classList.toggle("active", cometOn); }
      else if(act==="const"){ constOn=!constOn; btn.classList.toggle("active", constOn);
        document.getElementById("space-dock-float")?.querySelector('[data-act="const"]')?.classList.toggle("active", constOn);
        if(constOn){ drawConstellation(); svg.style.display="block"; } else svg.style.display="none";
      }
      else if(act==="astro"){ const a=document.getElementById("astro"); if(a){ a.style.display=a.style.display==="none"?"grid":"none"; btn.classList.toggle("active", a.style.display==="none"); } }
      else if(act==="ufo"){ ufoFly(); starBurst(80, 80, 12); }
      // feedback burst
      const r=btn.getBoundingClientRect(); starBurst(r.left+r.width/2, r.top+r.height/2, 8);
    });
  }
  window.addEventListener("resize", ()=>{ if(constOn) drawConstellation(); });

  // keyboard: Space = shoot, W = warp
  window.addEventListener("keydown", (e)=>{
    if(e.target.matches("input, textarea, select")) return;
    if(e.code==="Space"){ e.preventDefault(); shootOnce(); }
    if(e.key.toLowerCase()==="w"){ warp(); }
  });
}

document.addEventListener("DOMContentLoaded", ()=>{
  initLangSwitcher();
  initSearch();
  initNav();
  initReveal();
  initPersonnelModal();
  initPersonnelData();
  initCourseData();
  initNewsData();
  applyLang(currentLang);
  initFooter();
});
