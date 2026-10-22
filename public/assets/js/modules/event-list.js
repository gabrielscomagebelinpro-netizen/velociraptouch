export function renderEvents(events, eventsList, emptyEvents) {
  eventsList.innerHTML = "";
  emptyEvents.style.display = events.length ? "none" : "block";
  events.sort((a, b) => String(a.date || "").localeCompare(String(b.date || ""))).forEach((event, index) => {
    const date = event.date ? new Date(`${event.date}T12:00:00`) : null;
    const day = date ? date.toLocaleDateString("fr-FR", { day: "2-digit" }) : "—";
    const month = date ? date.toLocaleDateString("fr-FR", { month: "short" }).replace(".", "").toUpperCase() : "DATE";
    const weekday = date ? date.toLocaleDateString("fr-FR", { weekday: "long" }) : "date";
    const title = event.title || "Événement à préciser";
    const fullDate = date ? date.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" }) : "Date à préciser";
    const details = [fullDate, event.time, event.place].filter(Boolean).join(" · ") || "Informations à venir";
    const item = document.createElement("article");
    item.className = "event";
    item.innerHTML = `<div class="event-date"><strong>${day}</strong>${weekday} ${month}</div>
      <div class="event-main"><h3>${title}</h3><p>${details}</p></div>
      <span class="event-tag">${event.type || "À préciser"}</span>
      <button class="rsvp" data-event="${index}" type="button">Je serai là <span>＋</span></button>`;
    eventsList.appendChild(item);
  });
  eventsList.querySelectorAll(".rsvp").forEach((button) => {
    button.addEventListener("click", () => {
      button.classList.toggle("confirmed");
      button.innerHTML = button.classList.contains("confirmed") ? "Présence confirmée ✓" : "Je serai là ＋";
    });
  });
}
