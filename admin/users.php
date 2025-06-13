<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Test - Werkende Modals</title>
    <style>
        /* SIMPELE MODAL CSS DIE GEWOON WERKT */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .close:hover {
            color: #000;
        }
        
        /* BASIS STYLING */
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover {
            background: #005a87;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .user-card {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-actions {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h1>🧪 User Management Modal Test</h1>
    <p>Simpele test om te zorgen dat de modals gewoon werken!</p>
    
    <!-- TEST BUTTONS -->
    <div style="margin: 20px 0;">
        <button onclick="testModal()" class="btn">🧪 Test Basic Modal</button>
        <button onclick="resetUserForm(); openModal('userModal')" class="btn">➕ Nieuwe Gebruiker</button>
        <button onclick="editUser(123)" class="btn">✏️ Test Edit User</button>
        <button onclick="assignCourses(123)" class="btn btn-success">📚 Test Cursus Assignment</button>
    </div>
    
    <!-- FAKE USER CARDS FOR TESTING -->
    <div class="user-card">
        <h3>🧑‍💼 Jan Janssen</h3>
        <p><strong>Email:</strong> jan@example.com</p>
        <p><strong>Bedrijf:</strong> Test BV</p>
        <div class="user-actions">
            <button onclick="editUser(123)" class="btn">✏️ Bewerken</button>
            <button onclick="assignCourses(123)" class="btn btn-success">📚 Cursussen</button>
            <button onclick="deleteUser(123)" class="btn btn-warning">⚠️ Deactiveren</button>
        </div>
    </div>
    
    <div class="user-card">
        <h3>👩‍💼 Marie Pietersen</h3>
        <p><strong>Email:</strong> marie@example.com</p>
        <p><strong>Bedrijf:</strong> Innovatie NV</p>
        <div class="user-actions">
            <button onclick="editUser(456)" class="btn">✏️ Bewerken</button>
            <button onclick="assignCourses(456)" class="btn btn-success">📚 Cursussen</button>
            <button onclick="deleteUser(456)" class="btn btn-warning">⚠️ Deactiveren</button>
        </div>
    </div>

    <!-- BASIC TEST MODAL -->
    <div id="testModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="closeModal('testModal')">&times;</button>
            <h2>🧪 Test Modal</h2>
            <p>Deze modal werkt! Als je dit ziet, werken de modals correct.</p>
            <button onclick="closeModal('testModal')" class="btn">Sluiten</button>
        </div>
    </div>

    <!-- USER MODAL -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="closeModal('userModal')">&times;</button>
            <h2 id="userModalTitle">Nieuwe Gebruiker</h2>
            
            <form id="userForm">
                <div class="form-group">
                    <label for="name">Naam:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Telefoon:</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="company">Bedrijf:</label>
                    <input type="text" id="company" name="company">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notities:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn">💾 Opslaan</button>
                    <button type="button" onclick="closeModal('userModal')" class="btn" style="background: #6c757d;">Annuleren</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COURSE ASSIGNMENT MODAL -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <button class="close" onclick="closeModal('courseModal')">&times;</button>
            <h2 id="courseModalTitle">Cursus Toekenning</h2>
            <div id="courseContent">
                <p>🔄 Laden...</p>
            </div>
        </div>
    </div>

    <script>
        // SIMPELE WERKENDE FUNCTIES
        console.log('🚀 Modal Test JavaScript Loading...');
        
        function testModal() {
            console.log('🧪 Test modal opening...');
            openModal('testModal');
        }
        
        function openModal(modalId) {
            console.log('📖 Opening modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                console.log('✅ Modal opened:', modalId);
            } else {
                console.error('❌ Modal not found:', modalId);
            }
        }
        
        function closeModal(modalId) {
            console.log('📕 Closing modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                console.log('✅ Modal closed:', modalId);
            }
        }
        
        function editUser(userId) {
            console.log('✏️ Edit user:', userId);
            
            // FAKE DATA VOOR TEST
            const fakeUserData = {
                123: { name: 'Jan Janssen', email: 'jan@example.com', phone: '06-12345678', company: 'Test BV', notes: 'Test gebruiker' },
                456: { name: 'Marie Pietersen', email: 'marie@example.com', phone: '06-87654321', company: 'Innovatie NV', notes: 'Innovatieve gebruiker' }
            };
            
            const user = fakeUserData[userId];
            if (user) {
                // Vul formulier met data
                document.getElementById('name').value = user.name;
                document.getElementById('email').value = user.email;
                document.getElementById('phone').value = user.phone;
                document.getElementById('company').value = user.company;
                document.getElementById('notes').value = user.notes;
                
                // Update modal titel
                document.getElementById('userModalTitle').textContent = 'Gebruiker Bewerken: ' + user.name;
                
                // Open modal
                openModal('userModal');
            } else {
                alert('❌ Gebruiker niet gevonden');
            }
        }
        
        function deleteUser(userId) {
            console.log('🗑️ Delete user:', userId);
            if (confirm('Weet je zeker dat je deze gebruiker wilt deactiveren?')) {
                alert('✅ Gebruiker ' + userId + ' gedeactiveerd!');
            }
        }
        
        function assignCourses(userId) {
            console.log('📚 Assign courses for user:', userId);
            
            // Update modal title
            document.getElementById('courseModalTitle').textContent = 'Cursussen voor Gebruiker ' + userId;
            
            // Fake course data
            const courseContent = `
                <div style="margin-bottom: 20px;">
                    <h4>Beschikbare Cursussen:</h4>
                </div>
                
                <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">
                    <strong>🎯 AI Strategy Bootcamp</strong><br>
                    📅 15 Juli 2025 | 💰 €497<br>
                    <button onclick="alert('Cursus toegekend!')" class="btn" style="margin-top: 10px;">➕ Toekennen</button>
                </div>
                
                <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">
                    <strong>🚀 Digital Transformation</strong><br>
                    📅 22 Augustus 2025 | 💰 €697<br>
                    <button onclick="alert('Cursus toegekend!')" class="btn" style="margin-top: 10px;">➕ Toekennen</button>
                </div>
                
                <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">
                    <strong>💡 Innovation Workshop</strong><br>
                    📅 5 September 2025 | 💰 €397<br>
                    <button onclick="alert('Cursus toegekend!')" class="btn" style="margin-top: 10px;">➕ Toekennen</button>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <button onclick="closeModal('courseModal')" class="btn" style="background: #6c757d;">Sluiten</button>
                </div>
            `;
            
            document.getElementById('courseContent').innerHTML = courseContent;
            openModal('courseModal');
        }
        
        function resetUserForm() {
            console.log('🔄 Reset user form');
            document.getElementById('userForm').reset();
            document.getElementById('userModalTitle').textContent = 'Nieuwe Gebruiker';
        }
        
        // Form submit handler
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const userData = {};
            for (let [key, value] of formData.entries()) {
                userData[key] = value;
            }
            
            console.log('💾 Form data:', userData);
            alert('✅ Gebruiker opgeslagen!\n\nNaam: ' + userData.name + '\nEmail: ' + userData.email);
            closeModal('userModal');
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
        
        console.log('✅ All functions loaded and ready!');
    </script>
</body>
</html>