/******************************************************
 * Author: Samar Gill and Juan Vargas
 * Project: KU Campus Exchange
 * File: script.js
 * Description: Main script file for handling how everything functions 
 * on our Marketplace
 ******************************************************/
/* =========================
   Dark Mode / Search / Filter
   ========================= */
function toggleDarkMode() {
  document.body.classList.toggle("dark-mode");
}

function searchItems() {
  const input = (document.getElementById("search-bar")?.value || "").toLowerCase();
  document.querySelectorAll(".item").forEach(item => {
    const name = (item.querySelector("p")?.innerText || "").toLowerCase();
    item.style.display = !input || name.includes(input) ? "block" : "none";
  });
}

function filterCategory(category) {
  document.querySelectorAll(".item").forEach(item => {
    const cat = item.getAttribute("data-category");
    item.style.display = (category === "All" || cat === category) ? "block" : "none";
  });
}

/* =========================
   Item Modal Gallery + Offer
   ========================= */
let currentGallery = [];
let currentIndex = 0;
let currentItemId = null;
let currentSellerName = null;

function openModal(name, images, price, itemId = null, sellerName = null, desc = "") {
  const modal = document.getElementById("item-modal");
  if (!modal) return;

  // Title & price
  document.getElementById("modal-title").innerText = name || "";
  document.getElementById("modal-price").innerText = price || "";

  // Images
  if (!Array.isArray(images) || images.length === 0) {
    images = ["placeholder.png"];
  }
  currentGallery = images;
  currentIndex = 0;

  // Store item and seller
  currentItemId = itemId;
  currentSellerName = sellerName;

  // Seller line
  const sellerLine = document.getElementById("modal-seller");
  if (sellerLine) {
    sellerLine.textContent = sellerName ? "Seller: " + sellerName : "";
  }

  // Description under gallery 
  const descLine = document.getElementById("modal-desc");
  if (descLine) {
    descLine.textContent = desc || "";
  }

  // Prefill offer with numeric price 
  const offerInput = document.getElementById("modal-offer-input");
  if (offerInput) {
    const numeric = parseFloat(String(price).replace(/[^0-9.]/g, ""));
    if (Number.isFinite(numeric)) {
      offerInput.value = numeric.toFixed(2);
    } else {
      offerInput.value = "";
    }
  }

  // message/offer button
  const msgBtn = document.getElementById("message-seller-btn");
  if (msgBtn) {
    if (itemId) {
      msgBtn.style.display = "inline-flex";
      msgBtn.onclick = () => {
        let url = "messages.php?item_id=" + encodeURIComponent(itemId);
        const offerField = document.getElementById("modal-offer-input");
        if (offerField && offerField.value) {
          url += "&offer=" + encodeURIComponent(offerField.value);
        }
        window.location.href = url;
      };
    } else {
      msgBtn.style.display = "none";
      msgBtn.onclick = null;
    }
  }

  buildGallery();
  modal.style.display = "flex";
}

function buildGallery() {
  const main = document.getElementById("gallery-main");
  const thumbs = document.getElementById("gallery-thumbs");
  if (!main || !thumbs) return;

  main.innerHTML = "";
  thumbs.innerHTML = "";

  currentGallery.forEach((src, idx) => {
    const img = document.createElement("img");
    img.src = src;
    img.className = "gallery-image";
    img.style.display = idx === currentIndex ? "block" : "none";
    main.appendChild(img);

    const thumb = document.createElement("img");
    thumb.src = src;
    thumb.className = "thumb-image";
    thumb.addEventListener("click", () => {
      currentIndex = idx;
      updateGalleryDisplay();
    });
    thumbs.appendChild(thumb);
  });

  const prev = document.getElementById("gallery-prev");
  const next = document.getElementById("gallery-next");
  if (prev) prev.onclick = () => {
    currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
    updateGalleryDisplay();
  };
  if (next) next.onclick = () => {
    currentIndex = (currentIndex + 1) % currentGallery.length;
    updateGalleryDisplay();
  };

  updateGalleryDisplay();
}

function updateGalleryDisplay() {
  document.querySelectorAll("#gallery-main .gallery-image").forEach((img, i) => {
    img.style.display = i === currentIndex ? "block" : "none";
  });
  document.querySelectorAll("#gallery-thumbs .thumb-image").forEach((t, i) => {
    t.classList.toggle("active-thumb", i === currentIndex);
  });
}

function closeModal() {
  const modal = document.getElementById("item-modal");
  if (!modal) return;
  modal.style.display = "none";
  const m = document.getElementById("gallery-main");
  const t = document.getElementById("gallery-thumbs");
  if (m) m.innerHTML = "";
  if (t) t.innerHTML = "";
  currentGallery = [];
  currentIndex = 0;
  currentItemId = null;
  currentSellerName = null;
}

window.addEventListener("click", e => {
  const modal = document.getElementById("item-modal");
  if (modal && e.target === modal) closeModal();
});

/* =========================
   Upload Page  multi-images
   ========================= */
const dropArea = document.getElementById("drop-area");
const fileInput = document.getElementById("input-file");
const previewThumbs = document.getElementById("preview-thumbs");

const maxFiles = 5;
const MAX_PER_FILE = 20 * 1024 * 1024; // 20MB
const MAX_TOTAL = 25 * 1024 * 1024;    // 25MB

const selectedFiles = [];

// Only run on upload page
if (dropArea && fileInput && previewThumbs) {
  dropArea.addEventListener("click", () => fileInput.click());

  fileInput.addEventListener("change", (e) => handleFiles(e.target.files));

  dropArea.addEventListener("dragover", (e) => {
    e.preventDefault();
    dropArea.style.border = "2px dashed #ff8800";
  });
  dropArea.addEventListener("dragleave", (e) => {
    e.preventDefault();
    dropArea.style.border = "2px dashed #ccc";
  });
  dropArea.addEventListener("drop", (e) => {
    e.preventDefault();
    dropArea.style.border = "2px dashed #ccc";
    handleFiles(e.dataTransfer.files);
  });

  document.getElementById("upload-form")?.addEventListener("submit", (e) => {
    const files = selectedFiles.length ? selectedFiles : Array.from(fileInput.files || []);
    if (!files.length) return;

    let total = 0;
    for (const f of files) {
      total += f.size;
      if (f.size > MAX_PER_FILE) {
        e.preventDefault();
        alert(`${f.name} is too large (max 20MB).`);
        return;
      }
    }
    if (total > MAX_TOTAL) {
      e.preventDefault();
      alert(`Total upload too large (max 25MB).`);
      return;
    }

    if (selectedFiles.length) {
      e.preventDefault();
      const form = e.target;
      const fd = new FormData(form);
      fd.delete("item_images[]");
      selectedFiles.forEach(f => fd.append("item_images[]", f));

      fetch(form.action, { method: "POST", body: fd })
        .then(r => r.text())
        .then(html => { document.open(); document.write(html); document.close(); })
        .catch(err => alert("Upload failed: " + err));
    }
  });
}

function handleFiles(fileList) {
  const incoming = Array.from(fileList || []).filter(f => f.type.startsWith("image/"));
  if (!incoming.length) return;

  let base = selectedFiles.slice().concat(incoming);

  const seen = new Set();
  const unique = [];
  for (const f of base) {
    const k = `${f.name}|${f.size}|${f.lastModified}`;
    if (!seen.has(k)) { seen.add(k); unique.push(f); }
  }

  const limited = unique.slice(0, maxFiles);
  if (limited.length < unique.length) {
    alert(`Only the first ${maxFiles} images were kept.`);
  }

  selectedFiles.length = 0;
  limited.forEach(f => selectedFiles.push(f));

  try {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
  } catch {}

  previewThumbs.innerHTML = "";
  selectedFiles.forEach(displayPreview);
}

function displayPreview(file) {
  const reader = new FileReader();
  const container = document.createElement("div");
  container.className = "preview-item";

  const removeBtn = document.createElement("button");
  removeBtn.type = "button";
  removeBtn.className = "remove-thumb";
  removeBtn.textContent = "✕";

  reader.onload = (e) => {
    container.innerHTML = `
      <img src="${e.target.result}" alt="Preview" class="preview-img">
      <p class="preview-name">${file.name}</p>
    `;
    container.appendChild(removeBtn);
    previewThumbs.appendChild(container);

    removeBtn.addEventListener("click", () => {
      container.remove();
      for (let i = selectedFiles.length - 1; i >= 0; i--) {
        const f = selectedFiles[i];
        if (f.name === file.name && f.size === file.size && f.lastModified === file.lastModified) {
          selectedFiles.splice(i, 1);
        }
      }
      try {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
      } catch {}
    });
  };

  reader.readAsDataURL(file);
}
