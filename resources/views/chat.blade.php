<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat em Tempo Real</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.19.0/echo.iife.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .messages {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #e1e1e1;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message {
            margin-bottom: 10px;
            padding: 8px 12px;
            background-color: #f0f0f0;
            border-radius: 4px;
        }
        .message .user {
            font-weight: bold;
            color: #2c3e50;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        .input-group {
            margin-bottom: 10px;
        }
        input, button {
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .messages {
            border: 1px solid #ddd;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 10px;
        }
        .user {
            font-weight: bold;
            color: #3490dc;
        }
        .input-group {
            margin-bottom: 10px;
        }
        input {
            padding: 10px;
            width: 100%;
        }
        button {
            padding: 10px 20px;
            background-color: #3490dc;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
    @vite(['resources/js/app.js'])
</head>
<body>
    <div class="container">
        <h1>Chat em Tempo Real</h1>
        
        <div class="messages" id="messages">
            <!-- As mensagens aparecerão aqui -->
        </div>
        
        <form id="message-form">
            <div class="input-group">
                <input type="text" id="username" placeholder="Seu nome" required>
            </div>
            <div class="input-group">
                <input type="text" id="message" placeholder="Digite sua mensagem" required>
            </div>
            <button type="submit">Enviar</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            console.log('DOM carregado', window.Echo);
console.log('Echo está disponível?', typeof window.Echo !== 'undefined');
if (typeof window.Echo !== 'undefined') {
    console.log('Echo configurado com:', {
        broadcaster: window.Echo.connector.options.broadcaster,
        host: window.Echo.connector.options.host,
        port: window.Echo.connector.options.port,
        cluster: window.Echo.connector.options.cluster
    });
}
            // Verifica se o Echo está disponível
            if (typeof Echo === 'undefined') {
                console.error('Echo não está definido. Verifique se o app.js está carregado corretamente.');
                return;
            }

            console.log('Echo inicializado:', Echo);
            
            // Escutar o canal público
            Echo.channel('public-chat')
                .listen('.new-message', (data) => {
                    console.log('Mensagem recebida:', data);
                    addMessage(data.user, data.message);
                });

            // Função para adicionar mensagem à lista
            function addMessage(user, message) {
                const messagesDiv = document.getElementById('messages');
                const messageElement = document.createElement('div');
                messageElement.className = 'message';
                messageElement.innerHTML = `<span class="user">${user}:</span> ${message}`;
                messagesDiv.appendChild(messageElement);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }

            // Manipular envio do formulário
            document.getElementById('message-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const username = document.getElementById('username').value;
                const message = document.getElementById('message').value;
                
                if (message.trim() === '') return;
                
                // Enviar mensagem para o servidor
                axios.post('/send-message', {
                    user: username,
                    message: message
                })
                .then(response => {
                    console.log('Mensagem enviada com sucesso:', response.data);
                    document.getElementById('message').value = '';
                })
                .catch(error => {
                    console.error('Erro ao enviar mensagem:', error);
                });
            });
        });
    </script>


</body>
</html>