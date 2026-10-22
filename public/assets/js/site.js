const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".main-nav");

menuToggle?.addEventListener("click", () => navigation?.classList.toggle("open"));

const animatedElements = document.querySelectorAll(
    ".section-heading, .collective-content, .story-step, .event, .sponsor-card, .training-location, .contact-copy, .contact-form"
);

if (animatedElements.length && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    animatedElements.forEach((element, index) => {
        element.classList.add("scroll-reveal");
        element.style.setProperty("--reveal-delay", `${Math.min(index % 4, 3) * 90}ms`);
    });

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12 });

    animatedElements.forEach((element) => revealObserver.observe(element));
}

if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    const hero = document.querySelector(".hero");
    const heroArt = document.querySelector(".hero-art");
    let ticking = false;

    const updateHeroMotion = () => {
        if (hero && heroArt) {
            const offset = Math.min(window.scrollY * 0.08, 42);
            heroArt.style.setProperty("--hero-shift", `${offset}px`);
        }
        ticking = false;
    };

    window.addEventListener("scroll", () => {
        if (!ticking) {
            window.requestAnimationFrame(updateHeroMotion);
            ticking = true;
        }
    }, { passive: true });
}

const lightboxTriggers = document.querySelectorAll(".lightbox-trigger");

if (lightboxTriggers.length) {
    const lightbox = document.createElement("div");
    lightbox.className = "lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Image agrandie");
    lightbox.innerHTML = '<button class="lightbox-close" type="button" aria-label="Fermer l’image agrandie">×</button><img alt="" />';
    document.body.append(lightbox);

    const lightboxImage = lightbox.querySelector("img");
    const closeLightbox = () => {
        lightbox.classList.remove("is-open");
        document.body.classList.remove("lightbox-open");
    };

    const openLightbox = (image) => {
        lightboxImage.src = image.src;
        lightboxImage.alt = image.alt;
        lightbox.classList.add("is-open");
        document.body.classList.add("lightbox-open");
    };

    lightboxTriggers.forEach((image) => {
        image.addEventListener("click", () => openLightbox(image));
        image.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLightbox(image);
            }
        });
    });

    lightbox.querySelector(".lightbox-close").addEventListener("click", closeLightbox);
    lightbox.addEventListener("click", (event) => {
        if (event.target === lightbox) closeLightbox();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeLightbox();
    });
}

const photoInput = document.querySelector("#event-photos");
const photoDropzone = document.querySelector(".photo-dropzone");
const photoPreview = document.querySelector(".photo-upload-preview");
const photoStatus = document.querySelector(".photo-upload-status");
const photoForm = document.querySelector(".event-photo-upload");

if (photoInput && photoDropzone && photoPreview && photoStatus && photoForm) {
    const renderPhotoPreview = () => {
        photoPreview.replaceChildren();
        const files = Array.from(photoInput.files).filter((file) => file.type.startsWith("image/") || /\.(jpe?g|png|webp|heic|heif)$/i.test(file.name));
        photoStatus.textContent = files.length === 0
            ? "Aucune photo sélectionnée"
            : `${files.length} photo${files.length > 1 ? "s" : ""} prête${files.length > 1 ? "s" : ""} à être ajoutée${files.length > 1 ? "s" : ""}`;
        files.forEach((file, index) => {
            if (files.length > 5) {
                const name = document.createElement("span");
                name.className = "photo-preview-name";
                name.textContent = file.name;
                photoPreview.append(name);
                return;
            }
            const image = document.createElement("img");
            image.alt = file.name;
            image.src = URL.createObjectURL(file);
            photoPreview.append(image);
        });
    };

    photoInput.addEventListener("change", renderPhotoPreview);
    ["dragenter", "dragover"].forEach((eventName) => {
        photoDropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            photoDropzone.classList.add("is-dragging");
        });
    });
    ["dragleave", "drop"].forEach((eventName) => {
        photoDropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            photoDropzone.classList.remove("is-dragging");
        });
    });
    photoDropzone.addEventListener("drop", (event) => {
        const droppedFiles = Array.from(event.dataTransfer.files);
        if (droppedFiles.length) {
            const dataTransfer = new DataTransfer();
            droppedFiles.forEach((file) => dataTransfer.items.add(file));
            photoInput.files = dataTransfer.files;
            renderPhotoPreview();
        }
    });
    photoForm.addEventListener("submit", (event) => {
        if (!photoInput.files.length) {
            event.preventDefault();
            photoStatus.textContent = "Sélectionne au moins une photo avant de valider.";
        }
    });
}
