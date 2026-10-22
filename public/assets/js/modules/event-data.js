function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function nextTuesday() {
  const date = new Date();
  const daysUntilTuesday = (2 - date.getDay() + 7) % 7 || 7;
  date.setDate(date.getDate() + daysUntilTuesday);
  return formatDate(date);
}

export const defaultEvents = [
  { title: "Entraînement du mardi", date: nextTuesday(), time: "19:30", type: "Entraînement", place: "Stade Eric Béchu, Montpellier" },
  { title: "Tournoi des copains", date: "2026-10-17", time: "09:00", type: "Tournoi", place: "Complexe sportif Léo-Lagrange" },
  { title: "Apéro de rentrée", date: "2026-10-24", time: "19:00", type: "Vie du club", place: "Le Bar des Sports" }
];
