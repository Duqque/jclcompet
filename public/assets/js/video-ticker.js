/* Cadence d'image de la régie vidéo : une minuterie de « worker » n'est pas ralentie quand la fenêtre est masquée. */
setInterval(() => postMessage(0), 33);
