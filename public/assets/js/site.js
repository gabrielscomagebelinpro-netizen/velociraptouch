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

const photoInput = document.querySelector("#event-photos-input");
const photoDropzone = document.querySelector(".photo-dropzone");
const photoPreview = document.querySelector(".photo-upload-preview");
const photoStatus = document.querySelector(".photo-upload-status");
const photoForm = document.querySelector(".event-photo-upload");

if (photoInput && photoDropzone && photoPreview && photoStatus && photoForm) {
    const isImage = (file) => file.type.startsWith("image/") || /\.(jpe?g|png|webp|heic|heif)$/i.test(file.name);
    const plural = (count, word) => `${count} ${word}${count > 1 ? "s" : ""}`;
    const submitButton = photoForm.querySelector("button[type='submit']");
    // Les photos choisies en plusieurs fois (sélections ou dépôts successifs) s'accumulent ici.
    let selectedFiles = [];

    const renderPhotoPreview = () => {
        photoPreview.replaceChildren();
        photoStatus.textContent = selectedFiles.length === 0
            ? "Aucune photo sélectionnée"
            : `${plural(selectedFiles.length, "photo")} prête${selectedFiles.length > 1 ? "s" : ""} à être ajoutée${selectedFiles.length > 1 ? "s" : ""}`;
        selectedFiles.forEach((file) => {
            const name = document.createElement("span");
            name.className = "photo-preview-name";
            name.textContent = file.name;
            photoPreview.append(name);
        });
    };

    const addFiles = (files) => {
        const known = new Set(selectedFiles.map((file) => `${file.name}|${file.size}|${file.lastModified}`));
        Array.from(files).filter(isImage).forEach((file) => {
            const key = `${file.name}|${file.size}|${file.lastModified}`;
            if (!known.has(key)) {
                known.add(key);
                selectedFiles.push(file);
            }
        });
        renderPhotoPreview();
    };

    photoInput.addEventListener("change", () => {
        addFiles(photoInput.files);
        // Vide le champ pour pouvoir rouvrir le sélecteur et ajouter d'autres photos.
        photoInput.value = "";
    });
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
        if (event.dataTransfer?.files.length) addFiles(event.dataTransfer.files);
    });

    // Chaque photo part dans sa propre requête : aucune limite de nombre ni de taille totale par envoi.
    const uploadPhoto = async (file) => {
        const data = new FormData();
        data.append("_token", photoForm.querySelector("input[name='_token']").value);
        data.append("photos[]", file, file.name);
        try {
            const response = await fetch(photoForm.action, {
                method: "POST",
                body: data,
                headers: {"X-Requested-With": "XMLHttpRequest", "Accept": "application/json"},
                credentials: "same-origin",
            });
            const result = await response.json().catch(() => null);
            if (result) return result;
            return {uploaded: 0, errors: [response.status === 413
                ? `« ${file.name} » est trop lourde pour le serveur.`
                : `« ${file.name} » n'a pas pu être envoyée (erreur ${response.status}).`]};
        } catch {
            return {uploaded: 0, errors: [`« ${file.name} » n'a pas pu être envoyée (connexion interrompue).`]};
        }
    };

    photoForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!selectedFiles.length) {
            photoStatus.textContent = "Sélectionne au moins une photo avant de valider.";
            return;
        }

        const files = selectedFiles;
        let uploaded = 0;
        const errors = [];
        if (submitButton) submitButton.disabled = true;
        for (const [index, file] of files.entries()) {
            photoStatus.textContent = `Envoi de la photo ${index + 1} sur ${files.length}…`;
            const result = await uploadPhoto(file);
            uploaded += result.uploaded ?? 0;
            errors.push(...(result.errors ?? []));
        }

        if (errors.length === 0) {
            window.location.reload();
            return;
        }

        // En cas d'erreur, on garde le détail affiché ; les photos réussies sont déjà enregistrées.
        selectedFiles = [];
        renderPhotoPreview();
        if (submitButton) submitButton.disabled = false;
        photoStatus.textContent = `${plural(uploaded, "photo")} ajoutée${uploaded > 1 ? "s" : ""}, ${plural(errors.length, "erreur")}. Recharge la page pour voir les photos ajoutées.`;
        errors.forEach((message) => {
            const line = document.createElement("span");
            line.className = "photo-preview-name";
            line.textContent = message;
            photoPreview.append(line);
        });
    });
}

// Après un clic sur un bouton (formulaire envoyé, lien-bouton…), on revient à la même hauteur de page
// au lieu de remonter en haut, y compris quand on revient plus tard sur une page quittée par un bouton.
const SCROLL_POSITIONS_KEY = "velociraptouch:scroll-positions";
const SCROLL_POSITION_TTL = 30 * 60 * 1000;
const readScrollPositions = () => {
    try {
        return JSON.parse(sessionStorage.getItem(SCROLL_POSITIONS_KEY) || "{}");
    } catch {
        return {};
    }
};
const writeScrollPositions = (positions) => {
    try {
        sessionStorage.setItem(SCROLL_POSITIONS_KEY, JSON.stringify(positions));
    } catch {
        // Stockage indisponible (navigation privée…) : on garde le comportement par défaut.
    }
};

let pendingScrollPosition = null;
const rememberScrollPosition = () => {
    pendingScrollPosition = {path: window.location.pathname, y: window.scrollY, at: Date.now()};
};

document.addEventListener("submit", rememberScrollPosition, true);
document.addEventListener("click", (event) => {
    const button = event.target.closest("button, .button, .btn, .text-button, .text-link");
    if (!button) return;
    const href = button.getAttribute("href");
    // Les ancres internes (#agenda…) déplacent volontairement la page.
    if (href && new URL(href, window.location.href).pathname === window.location.pathname && href.includes("#")) return;
    rememberScrollPosition();
}, true);
// La position n'est enregistrée que si la page est réellement quittée (pas pour un téléchargement).
window.addEventListener("pagehide", () => {
    if (!pendingScrollPosition || Date.now() - pendingScrollPosition.at > 10000) return;
    const positions = readScrollPositions();
    positions[pendingScrollPosition.path] = {y: pendingScrollPosition.y, at: pendingScrollPosition.at};
    writeScrollPositions(positions);
});

const savedScrollPositions = readScrollPositions();
const savedScrollPosition = savedScrollPositions[window.location.pathname];
if (savedScrollPosition) {
    delete savedScrollPositions[window.location.pathname];
    writeScrollPositions(savedScrollPositions);
    if (Date.now() - savedScrollPosition.at < SCROLL_POSITION_TTL) {
        if ("scrollRestoration" in history) history.scrollRestoration = "manual";
        const restore = () => window.scrollTo({top: savedScrollPosition.y, behavior: "instant"});
        restore();
        // Les images chargées après coup peuvent décaler la page : on réajuste une fois tout chargé.
        window.addEventListener("load", restore, {once: true});
    }
}
