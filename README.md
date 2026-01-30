# 🎲 Plakoto Online Multiplayer

Ένα πλήρως λειτουργικό, διαδικτυακό παιχνίδι **Πλακωτού** για δύο παίκτες. Υλοποιημένο με **PHP**, **MySQL (MariaDB)** και **Vanilla JavaScript**.

Το παιχνίδι επιτρέπει σε δύο χρήστες από διαφορετικούς υπολογιστές να συνδεθούν στο ίδιο παιχνίδι (Host & Join) και να παίξουν σε πραγματικό χρόνο, χρησιμοποιώντας μηχανισμό **Polling** για συγχρονισμό.

## 🚀 Χαρακτηριστικά

* **Online Multiplayer:** Δυνατότητα παιχνιδιού μεταξύ δύο απομακρυσμένων παικτών.
* **Σύστημα Host / Join:** Ο Παίκτης 1 δημιουργεί το παιχνίδι και λαμβάνει ένα ID. Ο Παίκτης 2 συνδέεται χρησιμοποιώντας αυτό το ID.
* **Κανόνες Πλακωτού:**
    * Πλήρης υλοποίηση κίνησης.
    * **"Πλάκωμα" (Pinning):** Αντί να χτυπάτε το πούλι, το πατάτε και ο αντίπαλος δεν μπορεί να το κουνήσει.
    * Απαγόρευση κίνησης σε "πόρτες" (στήλες με 2+ πούλια του αντιπάλου).
* **State Management:** Η κατάσταση αποθηκεύεται στη βάση δεδομένων, εξασφαλίζοντας ότι δεν χάνεται το παιχνίδι με refresh.
* **Smart Polling:** Το Frontend ελέγχει έξυπνα για αλλαγές κάθε 1.5 δευτερόλεπτο, χωρίς να διακόπτει την αλληλεπίδραση του χρήστη (no UI flickering).
* **Security:** Χρήση κρυφών **Tokens** για να διασφαλιστεί ότι κάθε παίκτης παίζει μόνο όταν είναι η σειρά του.

## 🛠️ Τεχνολογίες

* **Backend:** PHP (Native, REST API logic)
* **Database:** MariaDB / MySQL
* **Frontend:** HTML5, CSS3 (Flexbox/Grid), JavaScript (Fetch API)

---

## ⚙️ Εγκατάσταση (Setup)

### 1. Βάση Δεδομένων
Δημιουργήστε τη βάση και τρέξτε το παρακάτω SQL script:

```sql
CREATE DATABASE plakoto_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plakoto_db;

CREATE TABLE game_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('waiting', 'active', 'ended') DEFAULT 'waiting',
    current_turn ENUM('white', 'black') DEFAULT 'white',
    p1_token VARCHAR(50) NULL,
    p2_token VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE board (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    position INT NOT NULL,
    piece_count INT DEFAULT 0,
    piece_color ENUM('white', 'black'),
    pinned_count INT DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES game_state(id) ON DELETE CASCADE
);
