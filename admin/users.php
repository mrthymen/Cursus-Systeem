<!DOCTYPE html>
<html>
<head>
    <title>MODAL DEBUG - Stap voor stap</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f0f0f0;
        }
        
        .debug-section {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #007cba;
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
        
        .btn:hover { background: #005a87; }
        
        /* STEP 1: ABSOLUUT SIMPELSTE MODAL */
        #debugModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 999999;
        }
        
        #debugModal .content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        
        #console {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>🔧 MODAL DEBUG SESSION</h1>
    <p>Laten we stap voor stap uitzoeken waarom de modals niet werken...</p>
    
    <div class="debug-section">
        <h3>⚡ STAP 1: BASIS TEST</h3>
        <p>Test of de simpelste modal werkt:</p>
        <button onclick="testBasicModal()" class="btn">🧪 Open Basic Modal</button>
        <div id="step1-result"></div>
    </div>
    
    <div class="debug-section">
        <h3>🔍 STAP 2: DOM INSPECT</h3>
        <p>Check of de modal elementen bestaan:</p>
        <button onclick="inspectDOM()" class="btn">🔍 Inspecteer DOM</button>
        <div id="step2-result"></div>
    </div>
    
    <div class="debug-section">
        <h3>📊 STAP 3: CSS CHECK</h3>
        <p>Test of CSS correct wordt toegepast:</p>
        <button onclick="checkCSS()" class="btn">🎨 Check CSS</button>
        <div id="step3-result"></div>
    </div>
    
    <div class="debug-section">
        <h3>⚙️ STAP 4: JAVASCRIPT TEST</h3>
        <p>Test alle JavaScript functies:</p>
        <button onclick="testJavaScript()" class="btn">⚡ Test JS</button>
        <div id="step4-result"></div>
    </div>
    
    <div class="debug-section">
        <h3>🖥️ LIVE CONSOLE</h3>
        <div id="console"></div>
        <button onclick="clearConsole()" class="btn">🧹 Clear Console</button>
    </div>

    <!-- SIMPELSTE MOGELIJKE MODAL -->
    <div id="debugModal">
        <div class="content">
            <button class="close-btn" onclick="closeDebugModal()">&times;</button>
            <h2>🎉 MODAL WERKT!</h2>
            <p>Als je dit ziet, werken de modals basic functionality.</p>
            <button onclick="closeDebugModal()" class="btn">Sluiten</button>
        </div>
    </div>

    <script>
        // CONSOLE FUNCTIE
        function log(message) {
            const console = document.getElementById('console');
            const time = new Date().toLocaleTimeString();
            console.innerHTML += `[${time}] ${message}\n`;
            console.scrollTop = console.scrollHeight;
            console.log(message); // Ook naar browser console
        }
        
        function clearConsole() {
            document.getElementById('console').innerHTML = '';
        }
        
        // STAP 1: BASIS MODAL TEST
        function testBasicModal() {
            log('🧪 STAP 1: Testing basic modal...');
            
            try {
                const modal = document.getElementById('debugModal');
                if (!modal) {
                    throw new Error('Modal element niet gevonden!');
                }
                
                log('✅ Modal element gevonden');
                
                // Force show
                modal.style.display = 'block';
                log('✅ Modal style.display = block gezet');
                
                // Check if actually visible
                const computed = window.getComputedStyle(modal);
                log(`📊 Computed display: ${computed.display}`);
                log(`📊 Computed visibility: ${computed.visibility}`);
                log(`📊 Computed z-index: ${computed.zIndex}`);
                
                document.getElementById('step1-result').innerHTML = '<span class="success">✅ Basic modal test uitgevoerd - check console</span>';
                
            } catch (error) {
                log(`❌ ERROR in basic modal test: ${error.message}`);
                document.getElementById('step1-result').innerHTML = `<span class="error">❌ ${error.message}</span>`;
            }
        }
        
        function closeDebugModal() {
            log('📕 Closing debug modal...');
            document.getElementById('debugModal').style.display = 'none';
            log('✅ Debug modal closed');
        }
        
        // STAP 2: DOM INSPECTION  
        function inspectDOM() {
            log('🔍 STAP 2: Inspecting DOM...');
            
            const modal = document.getElementById('debugModal');
            const results = [];
            
            if (modal) {
                results.push('✅ Modal element exists');
                results.push(`📍 Modal tagName: ${modal.tagName}`);
                results.push(`📍 Modal id: ${modal.id}`);
                results.push(`📍 Modal className: ${modal.className}`);
                results.push(`📍 Modal parent: ${modal.parentElement ? modal.parentElement.tagName : 'none'}`);
                
                const content = modal.querySelector('.content');
                if (content) {
                    results.push('✅ Modal content found');
                } else {
                    results.push('❌ Modal content NOT found');
                }
            } else {
                results.push('❌ Modal element NOT found');
            }
            
            results.forEach(result => log(result));
            document.getElementById('step2-result').innerHTML = results.join('<br>');
        }
        
        // STAP 3: CSS CHECK
        function checkCSS() {
            log('🎨 STAP 3: Checking CSS...');
            
            const modal = document.getElementById('debugModal');
            if (!modal) {
                log('❌ Modal not found for CSS check');
                return;
            }
            
            const computed = window.getComputedStyle(modal);
            const results = [];
            
            results.push(`Position: ${computed.position}`);
            results.push(`Display: ${computed.display}`);
            results.push(`Z-index: ${computed.zIndex}`);
            results.push(`Top: ${computed.top}`);
            results.push(`Left: ${computed.left}`);
            results.push(`Width: ${computed.width}`);
            results.push(`Height: ${computed.height}`);
            results.push(`Background: ${computed.backgroundColor}`);
            
            results.forEach(result => log(`📊 ${result}`));
            document.getElementById('step3-result').innerHTML = results.join('<br>');
        }
        
        // STAP 4: JAVASCRIPT TEST
        function testJavaScript() {
            log('⚡ STAP 4: Testing JavaScript...');
            
            const tests = [
                () => { 
                    log('Testing getElementById...');
                    return document.getElementById('debugModal') !== null;
                },
                () => {
                    log('Testing style manipulation...');
                    const modal = document.getElementById('debugModal');
                    modal.style.display = 'block';
                    const result = modal.style.display === 'block';
                    modal.style.display = 'none';
                    return result;
                },
                () => {
                    log('Testing onclick functions...');
                    return typeof testBasicModal === 'function';
                },
                () => {
                    log('Testing window.getComputedStyle...');
                    return typeof window.getComputedStyle === 'function';
                }
            ];
            
            const results = tests.map((test, index) => {
                try {
                    const result = test();
                    log(`✅ Test ${index + 1}: ${result ? 'PASS' : 'FAIL'}`);
                    return result;
                } catch (error) {
                    log(`❌ Test ${index + 1}: ERROR - ${error.message}`);
                    return false;
                }
            });
            
            const allPassed = results.every(r => r);
            document.getElementById('step4-result').innerHTML = allPassed 
                ? '<span class="success">✅ Alle JavaScript tests PASSED</span>'
                : '<span class="error">❌ Een of meer JavaScript tests FAILED</span>';
        }
        
        // INITIAL LOG
        log('🚀 Debug session started');
        log('📝 Klik op de knoppen om stap voor stap te testen...');
        
        // TEST OF CONSOLE WERKT
        setTimeout(() => {
            log('⏰ Auto-test: Console werkt na 1 seconde');
        }, 1000);
    </script>
</body>
</html>