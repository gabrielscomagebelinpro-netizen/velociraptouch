const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".main-nav");

// Le bouton annonce aux lecteurs d'écran si le menu est ouvert ou fermé ; Échap le referme.
const setMenuOpen = (open) => {
    navigation?.classList.toggle("open", open);
    menuToggle?.setAttribute("aria-expanded", String(open));
    menuToggle?.setAttribute("aria-label", open ? "Fermer le menu" : "Ouvrir le menu");
};
menuToggle?.addEventListener("click", () => setMenuOpen(!navigation?.classList.contains("open")));
document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && navigation?.classList.contains("open")) {
        setMenuOpen(false);
        menuToggle?.focus();
    }
});

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
    const closeButton = lightbox.querySelector(".lightbox-close");
    // Le focus entre dans la fenêtre à l'ouverture et revient sur l'image cliquée à la fermeture.
    let lastTrigger = null;
    const closeLightbox = () => {
        if (!lightbox.classList.contains("is-open")) return;
        lightbox.classList.remove("is-open");
        document.body.classList.remove("lightbox-open");
        lastTrigger?.focus();
    };

    const openLightbox = (image) => {
        lastTrigger = image;
        lightboxImage.src = image.src;
        lightboxImage.alt = image.alt;
        lightbox.classList.add("is-open");
        document.body.classList.add("lightbox-open");
        closeButton.focus();
    };

    // Seul élément interactif de la fenêtre : Tab reste dessus tant qu'elle est ouverte.
    lightbox.addEventListener("keydown", (event) => {
        if (event.key === "Tab") {
            event.preventDefault();
            closeButton.focus();
        }
    });

    lightboxTriggers.forEach((image) => {
        image.addEventListener("click", () => openLightbox(image));
        image.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLightbox(image);
            }
        });
    });

    closeButton.addEventListener("click", closeLightbox);
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

// Les albums peuvent contenir beaucoup de fichiers : aucune photo pleine résolution
// n'est demandée avant d'approcher sa vignette dans la fenêtre.
const lazyPhotos = document.querySelectorAll("img.photo-lazy[data-src]");
if (lazyPhotos.length) {
    const loadPhoto = (image) => {
        image.src = image.dataset.src;
        image.removeAttribute("data-src");
    };
    if ("IntersectionObserver" in window) {
        const photoObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                loadPhoto(entry.target);
                observer.unobserve(entry.target);
            });
        }, {rootMargin: "300px 0px"});
        lazyPhotos.forEach((image) => photoObserver.observe(image));
    } else {
        lazyPhotos.forEach(loadPhoto);
    }
}

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

// RGPD : la carte Google Maps n'est chargée qu'au clic, jamais à l'ouverture de la page.
document.querySelectorAll(".map-consent").forEach((placeholder) => {
    placeholder.querySelector("button")?.addEventListener("click", () => {
        const iframe = document.createElement("iframe");
        iframe.title = placeholder.dataset.mapTitle || "Carte";
        iframe.src = placeholder.dataset.mapSrc;
        iframe.loading = "lazy";
        iframe.referrerPolicy = "no-referrer-when-downgrade";
        placeholder.replaceWith(iframe);
    });
});

// Confirmation avant les actions sensibles (remplace les attributs onsubmit, interdits par la CSP).
// Fenêtre intégrée au site plutôt que window.confirm() : certains navigateurs intégrés et webviews bloquent
// les boîtes natives (confirm() y renvoie false sans rien afficher), ce qui empêchait toute suppression.
let confirmDialog = null;
const getConfirmDialog = () => {
    if (confirmDialog) return confirmDialog;
    confirmDialog = document.createElement("dialog");
    confirmDialog.className = "confirm-dialog";
    confirmDialog.innerHTML = `
        <form method="dialog">
            <p class="confirm-dialog-message"></p>
            <div class="confirm-dialog-actions">
                <button class="button button-outline" value="cancel">Annuler</button>
                <button class="button button-danger" value="confirm">Confirmer</button>
            </div>
        </form>`;
    document.body.append(confirmDialog);
    return confirmDialog;
};

document.addEventListener("submit", (event) => {
    const form = event.target;
    const message = form.dataset?.confirm;
    if (!message) return;
    if (form.dataset.confirmed === "1") {
        delete form.dataset.confirmed;
        return;
    }
    event.preventDefault();
    const submitter = event.submitter;
    const dialog = getConfirmDialog();
    dialog.querySelector(".confirm-dialog-message").textContent = message;
    dialog.returnValue = "";
    dialog.addEventListener("close", () => {
        if (dialog.returnValue !== "confirm") return;
        form.dataset.confirmed = "1";
        if (submitter && form.contains(submitter)) form.requestSubmit(submitter);
        else form.requestSubmit();
    }, {once: true});
    dialog.showModal();
    dialog.querySelector('[value="cancel"]').focus();
}, true);

// Le partage natif est réservé aux téléphones iPhone/Android qui savent réellement
// partager des fichiers. Sur PC ou navigateur incompatible, le ZIP est privilégié.
const photoShareButton = document.querySelector(".photo-share-button");
const photoZipLink = document.querySelector(".photo-zip-link");
const isMobileDevice = navigator.userAgentData?.mobile === true
    || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
    || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
const canSharePhotoFiles = typeof navigator.canShare === "function"
    && typeof navigator.share === "function";

if (photoShareButton && photoZipLink && (!isMobileDevice || !canSharePhotoFiles)) {
    photoShareButton.hidden = true;
    photoZipLink.classList.remove("photo-zip-secondary");
}

if (photoShareButton && photoZipLink && isMobileDevice && canSharePhotoFiles) {
    const photos = JSON.parse(photoShareButton.dataset.photos || "[]");
    const status = document.querySelector(".photo-share-status");
    const BATCH = 10;
    const probe = new File([new Blob(["x"], {type: "image/jpeg"})], "test.jpg", {type: "image/jpeg"});

    if (photos.length && navigator.canShare({files: [probe]})) {
        let batchIndex = 0;
        let preparedFiles = null;
        const batchCount = Math.ceil(photos.length / BATCH);
        const label = (text) => { photoShareButton.firstChild.textContent = `${text} `; };
        const setStatus = (text) => { status.hidden = !text; status.textContent = text; };

        const prepareBatch = async () => {
            preparedFiles = null;
            photoShareButton.disabled = true;
            const batch = photos.slice(batchIndex * BATCH, (batchIndex + 1) * BATCH);
            label(batchCount > 1 ? `Préparation des photos ${batchIndex * BATCH + 1} à ${batchIndex * BATCH + batch.length}…` : "Préparation des photos…");
            try {
                preparedFiles = await Promise.all(batch.map(async (photo) => {
                    const response = await fetch(photo.url, {credentials: "same-origin"});
                    if (!response.ok) throw new Error(String(response.status));
                    return new File([await response.blob()], photo.name, {type: photo.type});
                }));
                label(batchCount > 1
                    ? `Enregistrer les photos ${batchIndex * BATCH + 1} à ${batchIndex * BATCH + batch.length} sur ${photos.length}`
                    : `Enregistrer les ${photos.length} photo${photos.length > 1 ? "s" : ""}`);
                photoShareButton.disabled = false;
            } catch {
                label("Enregistrer toutes les photos");
                setStatus("Les photos n'ont pas pu être préparées. Utilise le ZIP ou le téléchargement individuel.");
            }
        };

        photoShareButton.hidden = false;
        if (window.matchMedia("(max-width: 760px)").matches) photoZipLink.classList.add("photo-zip-secondary");
        photoShareButton.addEventListener("click", async () => {
            if (!preparedFiles) {
                await prepareBatch();
            }
            if (!preparedFiles) return;
            try {
                await navigator.share({files: preparedFiles, title: "Photos Vélociraptouch"});
                batchIndex += 1;
                if (batchIndex < batchCount) {
                    setStatus(`Lot ${batchIndex} sur ${batchCount} terminé. Touche à nouveau le bouton pour la suite.`);
                    prepareBatch();
                } else {
                    batchIndex = 0;
                    setStatus("Toutes les photos ont été proposées à l'enregistrement.");
                    prepareBatch();
                }
            } catch (error) {
                // Partage annulé par l'utilisateur : rien à signaler.
                if (error?.name !== "AbortError") setStatus("L'enregistrement a échoué. Utilise le ZIP ou le téléchargement individuel.");
            }
        });
    }
}

// Planning du mois (section Agenda) : une bulle par événement, cliquable pour aller à sa fiche dans la liste.
const monthCalendar = document.getElementById("month-calendar");
if (monthCalendar) {
    let calendarEvents = [];
    try {
        calendarEvents = JSON.parse(document.getElementById("month-calendar-data")?.textContent || "[]");
    } catch {
        calendarEvents = [];
    }

    if (calendarEvents.length) {
        const pad = (value) => String(value).padStart(2, "0");
        const isoDay = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const monthIndex = (date) => date.getFullYear() * 12 + date.getMonth();
        const monthFormat = new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" });
        const dayFormat = new Intl.DateTimeFormat("fr-FR", { weekday: "long", day: "numeric", month: "long" });

        const eventsByDay = new Map();
        calendarEvents.forEach((event) => {
            if (!eventsByDay.has(event.date)) eventsByDay.set(event.date, []);
            eventsByDay.get(event.date).push(event);
        });

        const today = new Date();
        const todayIso = isoDay(today);
        const firstEventDate = new Date(`${calendarEvents[0].date}T00:00:00`);
        const lastEventDate = new Date(`${calendarEvents[calendarEvents.length - 1].date}T00:00:00`);
        // On peut remonter jusqu'au mois du plus ancien événement fourni (événements passés grisés).
        const firstMonth = Math.min(monthIndex(today), monthIndex(firstEventDate));
        const lastMonth = Math.max(monthIndex(today), monthIndex(lastEventDate));
        let shownMonth = monthIndex(today);

        const title = monthCalendar.querySelector(".month-calendar-title");
        const grid = monthCalendar.querySelector(".month-calendar-grid");
        const prevButton = monthCalendar.querySelector('[data-calendar-step="-1"]');
        const nextButton = monthCalendar.querySelector('[data-calendar-step="1"]');

        const render = () => {
            const year = Math.floor(shownMonth / 12);
            const month = shownMonth % 12;
            const firstDay = new Date(year, month, 1);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const leading = (firstDay.getDay() + 6) % 7; // semaine commençant le lundi
            const totalCells = Math.ceil((leading + daysInMonth) / 7) * 7;

            const label = monthFormat.format(firstDay);
            title.textContent = label.charAt(0).toUpperCase() + label.slice(1);
            prevButton.disabled = shownMonth <= firstMonth;
            nextButton.disabled = shownMonth >= lastMonth;
            grid.replaceChildren();

            for (let cell = 0; cell < totalCells; cell++) {
                const item = document.createElement("li");
                item.className = "month-calendar-day";
                const dayNumber = cell - leading + 1;

                if (dayNumber < 1 || dayNumber > daysInMonth) {
                    item.classList.add("is-outside");
                    item.setAttribute("aria-hidden", "true");
                    grid.append(item);
                    continue;
                }

                const date = new Date(year, month, dayNumber);
                const iso = isoDay(date);
                if (iso < todayIso) item.classList.add("is-past");
                if (iso === todayIso) item.classList.add("is-today");

                const number = document.createElement("span");
                number.className = "month-calendar-number";
                number.textContent = dayNumber;
                number.setAttribute("aria-hidden", "true");
                item.append(number);

                const dayEvents = eventsByDay.get(iso) || [];
                if (!dayEvents.length) {
                    item.setAttribute("aria-hidden", "true");
                } else {
                    item.classList.add("has-events");
                    dayEvents.forEach((event) => {
                        // Événement à venir : ancre vers sa fiche. Passé : lien vers ses photos (admins) ou simple étiquette.
                        const bubble = document.createElement(event.url ? "a" : "span");
                        bubble.className = "month-calendar-event";
                        if (event.url) bubble.href = event.url;
                        const status = event.past ? (event.url ? " (terminé, voir les photos)" : " (terminé)") : "";
                        if (event.past) bubble.classList.add("is-past");
                        bubble.title = event.title + status;
                        bubble.setAttribute("aria-label", `${event.title}, ${dayFormat.format(date)}${status}`);
                        bubble.textContent = event.title;
                        item.append(bubble);
                    });
                }
                grid.append(item);
            }
        };

        monthCalendar.querySelectorAll("[data-calendar-step]").forEach((button) => {
            button.addEventListener("click", () => {
                shownMonth = Math.min(lastMonth, Math.max(firstMonth, shownMonth + Number(button.dataset.calendarStep)));
                render();
            });
        });

        // Un clic sur une bulle fait défiler jusqu'à l'événement et le met brièvement en évidence.
        grid.addEventListener("click", (clickEvent) => {
            const bubble = clickEvent.target.closest(".month-calendar-event");
            const href = bubble?.getAttribute("href") || "";
            const target = href.startsWith("#event-") && document.querySelector(href);
            if (!target) return;
            target.classList.remove("is-highlighted");
            void target.offsetWidth;
            target.classList.add("is-highlighted");
            setTimeout(() => target.classList.remove("is-highlighted"), 2200);
        });

        render();
        monthCalendar.hidden = false;
    }
}
