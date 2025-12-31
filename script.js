// Configurações
const API_URL = 'api.php';
const MAX_RECENT = 5;

// Classe principal da aplicação
class BibliaIPA {
    constructor() {
        this.state = {
            livros: [],
            currentLivro: '',
            currentCapitulo: '',
            recentChapters: JSON.parse(localStorage.getItem('recentChapters')) || [],
            darkMode: localStorage.getItem('darkMode') === 'true',
            viewMode: 'standard'
        };

        this.elements = this.initializeElements();
        this.uiManager = new UIManager(this.elements);
        this.chapterManager = new ChapterManager(this);
        this.recentManager = new RecentManager(this);
        this.modalManager = new ModalManager(this);
        this.themeManager = new ThemeManager(this);
        this.eventManager = new EventManager(this);
        this.speechManager = new SpeechManager();
        this.notificationManager = new NotificationManager();
        this.pendingWordsManager = new PendingWordsManager(this);
    }

    initializeElements() {
        return {
            livroSelect: document.getElementById('livroSelect'),
            capituloSelect: document.getElementById('capituloSelect'),
            btnLoadChapter: document.getElementById('btnLoadChapter'),
            loadingIndicator: document.getElementById('loadingIndicator'),
            chapterContent: document.getElementById('chapterContent'),
            wordCount: document.getElementById('wordCount'),
            ipaCount: document.getElementById('ipaCount'),
            currentChapter: document.getElementById('currentChapter'),
            chapterStats: document.getElementById('chapterStats'),
            recentList: document.getElementById('recentList'),
            themeToggle: document.getElementById('themeToggle'),
            toggleView: document.getElementById('toggleView'),
            wordModal: document.getElementById('wordModal'),
            modalWord: document.getElementById('modalWord'),
            modalIPA: document.getElementById('modalIPA'),
            modalStatus: document.getElementById('modalStatus'),
            playAudio: document.getElementById('playAudio'),
            printChapter: document.getElementById('printChapter')
        };
    }

    async init() {
        await this.loadLivros();
        this.eventManager.setupEventListeners();
        this.themeManager.applyTheme();
        this.recentManager.updateRecentList();
    }

    async loadLivros() {
        try {
            const response = await fetch(`${API_URL}?action=get_livros`);
            const data = await response.json();

            if (data.success) {
                this.state.livros = data.livros;
                this.uiManager.renderLivrosSelect(this.state.livros);
            } else {
                this.notificationManager.show('Erro ao carregar livros', 'error');
            }
        } catch (error) {
            this.notificationManager.show('Erro de conexão: ' + error.message, 'error');
        }
    }

    getState() {
        return this.state;
    }

    setState(newState) {
        this.state = { ...this.state, ...newState };
    }
}

// Gerenciador de Interface do Usuário
class UIManager {
    constructor(elements) {
        this.elements = elements;
    }

    renderLivrosSelect(livros) {
        this.elements.livroSelect.innerHTML = '<option value="">Selecione um livro</option>';

        livros.forEach(livro => {
            const option = document.createElement('option');
            option.value = livro.livro;
            option.textContent = livro.livro;
            option.dataset.capitulos = livro.capitulos;
            this.elements.livroSelect.appendChild(option);
        });

        this.elements.livroSelect.disabled = false;
    }

    renderCapitulosSelect(totalCapitulos) {
        this.elements.capituloSelect.innerHTML = '<option value="">Selecione um capítulo</option>';

        for (let i = 1; i <= totalCapitulos; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `Capítulo ${i}`;
            this.elements.capituloSelect.appendChild(option);
        }

        this.elements.capituloSelect.disabled = false;
    }

    showLoading(show) {
        this.elements.loadingIndicator.style.display = show ? 'flex' : 'none';
        this.elements.btnLoadChapter.disabled = show;
    }

    updateStats(paragrafos) {
        const stats = this.calculateStats(paragrafos);

        this.elements.wordCount.textContent = stats.totalWords;
        this.elements.ipaCount.textContent = stats.wordsWithIPA;
        this.elements.chapterStats.textContent =
            `${stats.totalWords} palavras • ${stats.wordsWithIPA} com IPA`;

        return stats;
    }

    calculateStats(paragrafos) {
        let totalWords = 0;
        let wordsWithIPA = 0;

        paragrafos.forEach(paragrafo => {
            paragrafo.ingles.forEach(palavra => {
                if (palavra.is_composite) {
                    palavra.parts.forEach(part => {
                        totalWords++;
                        if (part.has_ipa) wordsWithIPA++;
                    });
                } else {
                    totalWords++;
                    if (palavra.has_ipa) wordsWithIPA++;
                }
            });
        });

        return { totalWords, wordsWithIPA };
    }

    updateChapterTitle(livro, capitulo) {
        this.elements.currentChapter.textContent = `${livro} - Capítulo ${capitulo}`;
    }

    toggleLoadButton(enable) {
        this.elements.btnLoadChapter.disabled = !enable;
    }
}

// Gerenciador de Capítulos
class ChapterManager {
    constructor(app) {
        this.app = app;
        this.ui = app.uiManager;
        this.notification = app.notificationManager;
    }

    async loadChapter(livro, capitulo) {
        this.ui.showLoading(true);

        try {
            const response = await fetch(
                `${API_URL}?action=get_capitulo&livro=${encodeURIComponent(livro)}&capitulo=${capitulo}`
            );

            const data = await response.json();

            if (data.success) {
                this.renderChapter(data);
                this.ui.updateStats(data.paragrafos);
                this.app.recentManager.addToRecent(livro, capitulo, data.paragrafos.length);

                // Coletar palavras pendentes
                this.app.pendingWordsManager.collectPendingWords(data.paragrafos);

                this.notification.show(`Capítulo ${capitulo} de ${livro} carregado`, 'success');
            } else {
                this.notification.show('Erro: ' + (data.error || 'Desconhecido'), 'error');
            }
        } catch (error) {
            this.notification.show('Erro de conexão: ' + error.message, 'error');
        } finally {
            this.ui.showLoading(false);
        }
    }

    renderChapter(data) {
        this.ui.updateChapterTitle(data.livro, data.capitulo);

        let html = '';

        data.paragrafos.forEach((paragrafo, index) => {
            html += this.renderParagraph(paragrafo, index);
        });

        this.app.elements.chapterContent.innerHTML = html;
        this.attachWordEvents(data.paragrafos);
    }

    renderParagraph(paragrafo, index) {
        return `
                <div class="paragraph" data-paragraph="${index}">
                    <div class="paragraph-content">
                        <div class="words-row">
                            ${paragrafo.ingles.map((palavra, wordIndex) =>
            this.renderWord(palavra, index, wordIndex)).join('')}
                        </div>
                        <div class="translation-row">
                            <div class="translation-text">
                                ${paragrafo.portugues}
                            </div>
                        </div>
                    </div>
                </div>
            `;
    }

    renderWord(palavra, paraIndex, wordIndex) {
        if (palavra.is_composite) {
            return this.renderCompositeWord(palavra, paraIndex, wordIndex);
        } else {
            return this.renderSimpleWord(palavra, paraIndex, wordIndex);
        }
    }

    renderCompositeWord(palavra, paraIndex, wordIndex) {
        const ipaText = palavra.parts.map(p => p.has_ipa ? p.ipa : p.original).join(' - ');

        return `
                <div class="word-item composite-word" 
                     data-para="${paraIndex}" 
                     data-word="${wordIndex}"
                     title="Palavra composta - Clique para detalhes">
                    <div class="word-original">${palavra.original}&nbsp;</div>
                    <div class="word-ipa">${ipaText}&nbsp;</div>
                </div>
            `;
    }

    renderSimpleWord(palavra, paraIndex, wordIndex) {
        const statusClass = palavra.has_ipa ? 'found' : 'not-found';
        const ipaText = palavra.has_ipa ? palavra.ipa : palavra.original;

        return `
                <div class="word-item ${statusClass}" 
                     data-para="${paraIndex}" 
                     data-word="${wordIndex}"
                     title="${palavra.original} - Clique para detalhes">
                    <div class="word-original">${palavra.original}&nbsp;</div>
                    <div class="word-ipa">${ipaText}&nbsp;</div>
                </div>
            `;
    }

    attachWordEvents(paragrafos) {
        document.querySelectorAll('.word-item').forEach(item => {
            item.addEventListener('click', () => {
                const paraIndex = item.dataset.para;
                const wordIndex = item.dataset.word;
                this.app.modalManager.showWordDetail(paraIndex, wordIndex, paragrafos);
            });
        });
    }
    renderChapter(data) {
        this.ui.updateChapterTitle(data.livro, data.capitulo);

        let html = '';

        data.paragrafos.forEach((paragrafo, index) => {
            html += this.renderParagraph(paragrafo, index);
        });

        this.app.elements.chapterContent.innerHTML = html;
        this.attachWordEvents(data.paragrafos);
        
        // Mostrar navegação sticky quando houver conteúdo
        if (data.paragrafos.length > 0) {
            this.showStickyNavigation(true);
        }
    }

    showStickyNavigation(show) {
        const stickyNav = document.querySelector('.sticky-navigation');
        if (stickyNav) {
            stickyNav.style.display = show ? 'flex' : 'none';
        }
    }
}

// Gerenciador de Capítulos Recentes
class RecentManager {
    constructor(app) {
        this.app = app;
        this.ui = app.uiManager;
        this.elements = app.elements;
    }

    addToRecent(livro, capitulo, paragraphCount) {
        const recentItem = {
            livro,
            capitulo,
            date: new Date().toISOString(),
            paragraphs: paragraphCount
        };

        // Remover se já existir
        this.app.state.recentChapters = this.app.state.recentChapters.filter(
            item => !(item.livro === livro && item.capitulo === capitulo)
        );

        // Adicionar no início
        this.app.state.recentChapters.unshift(recentItem);

        // Manter apenas os últimos MAX_RECENT
        this.app.state.recentChapters = this.app.state.recentChapters.slice(0, MAX_RECENT);

        // Salvar no localStorage
        localStorage.setItem('recentChapters', JSON.stringify(this.app.state.recentChapters));

        // Atualizar lista
        this.updateRecentList();
    }

    updateRecentList() {
        if (this.app.state.recentChapters.length === 0) {
            this.elements.recentList.innerHTML = `
                    <div class="empty-recent">
                        <i class="fas fa-clock"></i>
                        <p>Nenhum capítulo recente</p>
                    </div>
                `;
            return;
        }

        let html = '';
        this.app.state.recentChapters.forEach(item => {
            html += this.renderRecentItem(item);
        });

        this.elements.recentList.innerHTML = html;
        this.attachRecentItemEvents();
    }

    renderRecentItem(item) {
        const date = new Date(item.date);
        const timeString = date.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
                <div class="recent-item" data-livro="${item.livro}" data-capitulo="${item.capitulo}">
                    <div class="recent-icon">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="recent-info">
                        <div class="recent-title">${item.livro} ${item.capitulo}</div>
                        <div class="recent-meta">${timeString} • ${item.paragraphs} parágrafos</div>
                    </div>
                    <button class="recent-load" title="Carregar este capítulo">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            `;
    }

    attachRecentItemEvents() {
        document.querySelectorAll('.recent-load').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.recent-item');
                const livro = item.dataset.livro;
                const capitulo = item.dataset.capitulo;

                this.loadRecentChapter(livro, capitulo);
            });
        });
    }

    loadRecentChapter(livro, capitulo) {
        this.app.elements.livroSelect.value = livro;
        this.app.elements.livroSelect.dispatchEvent(new Event('change'));

        setTimeout(() => {
            this.app.elements.capituloSelect.value = capitulo;
            this.app.elements.capituloSelect.dispatchEvent(new Event('change'));
            this.app.chapterManager.loadChapter(livro, capitulo);
        }, 100);
    }
}

// Gerenciador de Modal
class ModalManager {
    constructor(app) {
        this.app = app;
        this.elements = app.elements;
        this.speech = app.speechManager;
        this.isOpen = false;
    }

    showWordDetail(paraIndex, wordIndex, paragrafos) {
        const palavra = paragrafos[paraIndex].ingles[wordIndex];

        this.elements.modalWord.textContent = palavra.original;

        if (palavra.is_composite) {
            this.showCompositeWordDetails(palavra);
        } else {
            this.showSimpleWordDetails(palavra);
        }

        this.openModal();
    }

    showCompositeWordDetails(palavra) {
        const ipaParts = palavra.parts.map(p => p.has_ipa ? p.ipa : p.original);
        this.elements.modalIPA.textContent = ipaParts.join(' - ');
        this.elements.modalStatus.textContent = 'Palavra Composta';
        this.elements.modalStatus.className = 'status-badge composite';
    }

    showSimpleWordDetails(palavra) {
        this.elements.modalIPA.textContent = palavra.has_ipa ? palavra.ipa : palavra.original;
        this.elements.modalStatus.textContent = palavra.has_ipa ? 'Disponível' : 'Não encontrada';
        this.elements.modalStatus.className = palavra.has_ipa ?
            'status-badge found' : 'status-badge not-found';
    }

    openModal() {
        this.elements.wordModal.style.display = 'flex';
        setTimeout(() => {
            this.elements.wordModal.classList.add('show');
            this.isOpen = true;
        }, 10);
    }

    closeModal() {
        this.elements.wordModal.classList.remove('show');
        setTimeout(() => {
            this.elements.wordModal.style.display = 'none';
            this.isOpen = false;
        }, 300);
    }

    playPronunciation() {
        const word = this.elements.modalWord.textContent;
        this.speech.playWord(word);

        // Mudar ícone durante a fala
        const icon = this.elements.playAudio.querySelector('i');
        icon.className = 'fas fa-volume-up';

        setTimeout(() => {
            icon.className = 'fas fa-play';
        }, 1000);
    }
}

// Gerenciador de Tema
class ThemeManager {
    constructor(app) {
        this.app = app;
        this.elements = app.elements;
    }

    toggleTheme() {
        this.app.state.darkMode = !this.app.state.darkMode;
        this.applyTheme();
        localStorage.setItem('darkMode', this.app.state.darkMode);
    }

    applyTheme() {
        if (this.app.state.darkMode) {
            document.body.classList.add('dark-mode');
            this.elements.themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            document.body.classList.remove('dark-mode');
            this.elements.themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
    }

    toggleViewMode() {
        this.app.state.viewMode = this.app.state.viewMode === 'standard' ? 'compact' : 'standard';
        document.body.setAttribute('data-view', this.app.state.viewMode);
        this.app.notificationManager.show(
            `Modo de visualização: ${this.app.state.viewMode === 'standard' ? 'Padrão' : 'Compacto'}`,
            'info'
        );
    }
}

// Gerenciador de Eventos
class EventManager {
    constructor(app) {
        this.app = app;
        this.ui = app.uiManager;
        this.modal = app.modalManager;
        this.theme = app.themeManager;
        this.chapter = app.chapterManager;
    }

    setupEventListeners() {
        this.setupLivroSelect();
        this.setupCapituloSelect();
        this.setupLoadButton();
        this.setupThemeToggle();
        this.setupViewToggle();
        this.setupModalEvents();
        this.setupAudioButton();
        this.setupPrintButton();
        this.setupKeyboardEvents();
        this.setupWordClickEvents();
        this.setupChapterNavigation();
        this.setupChapterButtons();
    }

    setupLivroSelect() {
        this.app.elements.livroSelect.addEventListener('change', (e) => {
            const livro = e.target.value;
            const selectedOption = e.target.options[e.target.selectedIndex];
            const capitulos = parseInt(selectedOption.dataset.capitulos) || 1;

            this.app.setState({ currentLivro: livro });
            this.ui.renderCapitulosSelect(capitulos);
            this.ui.toggleLoadButton(livro && this.app.elements.capituloSelect.value);
        });
    }

    setupCapituloSelect() {
        this.app.elements.capituloSelect.addEventListener('change', (e) => {
            this.app.setState({ currentCapitulo: e.target.value });
            this.ui.toggleLoadButton(this.app.state.currentLivro && e.target.value);
        });
    }

    setupLoadButton() {
        this.app.elements.btnLoadChapter.addEventListener('click', () => {
            if (this.app.state.currentLivro && this.app.state.currentCapitulo) {
                this.chapter.loadChapter(
                    this.app.state.currentLivro,
                    this.app.state.currentCapitulo
                );
            }
        });
    }

    setupThemeToggle() {
        this.app.elements.themeToggle.addEventListener('click', () => {
            this.theme.toggleTheme();
        });
    }

    setupViewToggle() {
        this.app.elements.toggleView.addEventListener('click', () => {
            this.theme.toggleViewMode();
        });
    }

    setupModalEvents() {
        document.querySelector('.modal-close').addEventListener('click', () => {
            this.modal.closeModal();
        });

        this.app.elements.wordModal.addEventListener('click', (e) => {
            if (e.target === this.app.elements.wordModal) {
                this.modal.closeModal();
            }
        });
    }

    setupAudioButton() {
        this.app.elements.playAudio.addEventListener('click', () => {
            this.modal.playPronunciation();
        });
    }

    setupPrintButton() {
        this.app.elements.printChapter.addEventListener('click', () => {
            window.print();
        });
    }

    setupKeyboardEvents() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.isOpen) {
                this.modal.closeModal();
            }
        });
    }

    setupWordClickEvents() {
        document.addEventListener('click', (e) => {
            const target = e.target;

            if (target.classList.contains('word-original')) {
                const word = target.textContent.trim();
                this.app.speechManager.playWord(word);
            }
        });
    }

    setupChapterNavigation() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') {
                this.navigateChapter('next');
            } else if (e.key === 'ArrowLeft') {
                this.navigateChapter('prev');
            }
        });
    }

    setupChapterButtons() {
        document.getElementById('prevChapter').addEventListener('click', () => {
            this.navigateChapter('prev');
        });

        document.getElementById('nextChapter').addEventListener('click', () => {
            this.navigateChapter('next');
        });

        document.getElementById('prevChapterSticky').addEventListener('click', () => {
            this.navigateChapter('prev');

            // Carregar o capítulo anterior
            const livro = this.app.elements.livroSelect.value;
            const capitulo = this.app.elements.capituloSelect.value;
            if (livro && capitulo) {
                this.app.chapterManager.loadChapter(livro, capitulo);
            }
        });

        document.getElementById('nextChapterSticky').addEventListener('click', () => {
            this.navigateChapter('next');

            // Carregar o próximo capítulo
            const livro = this.app.elements.livroSelect.value;
            const capitulo = this.app.elements.capituloSelect.value;
            if (livro && capitulo) {
                this.app.chapterManager.loadChapter(livro, capitulo);
            }
        });
    }

    navigateChapter(direction) {
        const { currentLivro, currentCapitulo } = this.app.state;
        const livroSelect = this.app.elements.livroSelect;
        const capituloSelect = this.app.elements.capituloSelect;

        // Se não houver livro selecionado, não fazer nada
        if (!currentLivro || !currentCapitulo) {
            this.app.notificationManager.show('Selecione um capítulo primeiro', 'warning');
            return;
        }

        const livros = Array.from(livroSelect.options).filter(option => option.value !== "");
        const currentLivroIndex = Array.from(livroSelect.options).findIndex(option => option.value === currentLivro);
        const currentCapituloNum = parseInt(currentCapitulo);
        const totalCapitulos = parseInt(livroSelect.options[currentLivroIndex]?.dataset.capitulos || 1);

        let newLivro = currentLivro;
        let newCapitulo = currentCapituloNum;

        if (direction === 'next') {
            if (currentCapituloNum < totalCapitulos) {
                newCapitulo = currentCapituloNum + 1;
            } else {
                // Ir para o primeiro capítulo do próximo livro
                const nextLivroIndex = (currentLivroIndex + 1) % livros.length;
                if (nextLivroIndex > 0) { // Pular a opção vazia
                    newLivro = livroSelect.options[nextLivroIndex].value;
                    newCapitulo = 1;
                }
            }
        } else if (direction === 'prev') {
            if (currentCapituloNum > 1) {
                newCapitulo = currentCapituloNum - 1;
            } else {
                // Ir para o último capítulo do livro anterior
                const prevLivroIndex = (currentLivroIndex - 1 + livros.length) % livros.length;
                if (prevLivroIndex > 0) { // Pular a opção vazia
                    newLivro = livroSelect.options[prevLivroIndex].value;
                    const prevTotalCapitulos = parseInt(livroSelect.options[prevLivroIndex]?.dataset.capitulos || 1);
                    newCapitulo = prevTotalCapitulos;
                }
            }
        }

        // Atualizar os selects
        livroSelect.value = newLivro;
        livroSelect.dispatchEvent(new Event('change'));

        // Dar um pequeno delay para garantir que os capítulos foram carregados
        setTimeout(() => {
            capituloSelect.value = newCapitulo;
            capituloSelect.dispatchEvent(new Event('change'));

            // Carregar o novo capítulo
            this.app.chapterManager.loadChapter(newLivro, newCapitulo);
        }, 100);
    }
}

// Gerenciador de Fala
class SpeechManager {
    playWord(word) {
        const utterance = new SpeechSynthesisUtterance(word);
        utterance.lang = 'en-US';
        utterance.rate = 0.9;

        speechSynthesis.speak(utterance);
    }
}

// Gerenciador de Notificações
class NotificationManager {
    show(message, type = 'info') {
        const iconMap = {
            error: 'exclamation-triangle',
            success: 'check-circle',
            info: 'info-circle'
        };

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${iconMap[type]}"></i>
                    <span>${message}</span>
                </div>
            `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('hide');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
}
// Gerenciador de Palavras Pendentes
class PendingWordsManager {
    constructor(app) {
        this.app = app;
        this.ui = app.uiManager;
        this.elements = app.elements;
        this.pendingWords = [];
        this.initializeUI();
    }

    initializeUI() {
        // Adiciona botão na sidebar
        this.createPendingWordsButton();
        this.createPendingWordsPanel();
    }

    createPendingWordsButton() {
        const button = document.createElement('button');
        button.className = 'pending-btn';
        button.innerHTML = `
            <i class="fas fa-edit"></i>
            <span>Palavras Pendentes</span>
            <span class="pending-count">0</span>
        `;

        button.addEventListener('click', () => {
            this.togglePendingPanel();
        });

        // Adiciona ao sidebar
        const sidebar = document.querySelector('.sidebar');
        const recentSection = document.querySelector('.recent-chapters');
        sidebar.insertBefore(button, recentSection);
    }

    createPendingWordsPanel() {
        const panel = document.createElement('div');
        panel.className = 'pending-panel';
        panel.innerHTML = `
            <div class="pending-header">
                <h3><i class="fas fa-edit"></i> Palavras Pendentes</h3>
                <button class="close-panel">&times;</button>
            </div>
            <div class="pending-controls">
                <div class="search-box">
                    <input type="text" placeholder="Buscar palavras..." class="search-input">
                    <i class="fas fa-search"></i>
                </div>
                <div class="filter-controls">
                    <select class="sort-select">
                        <option value="alpha">A → Z</option>
                        <option value="alpha-desc">Z → A</option>
                        <option value="frequency">Frequência</option>
                    </select>
                </div>
            </div>
            <div class="pending-list"></div>
            <div class="pending-actions">
                <button class="btn-edit-selected" disabled>
                    <i class="fas fa-edit"></i> Editar Selecionadas
                </button>
                <button class="btn-edit-all">
                    <i class="fas fa-keyboard"></i> Editar Todas
                </button>
            </div>
        `;

        document.body.appendChild(panel);

        // Event listeners do painel
        panel.querySelector('.close-panel').addEventListener('click', () => {
            this.hidePendingPanel();
        });

        panel.querySelector('.btn-edit-all').addEventListener('click', () => {
            this.editAllPendingWords();
        });

        panel.querySelector('.btn-edit-selected').addEventListener('click', () => {
            this.editSelectedWords();
        });

        panel.querySelector('.search-input').addEventListener('input', (e) => {
            this.filterWords(e.target.value);
        });

        panel.querySelector('.sort-select').addEventListener('change', (e) => {
            this.sortWords(e.target.value);
        });
    }

    collectPendingWords(paragrafos) {
        const pendingMap = new Map();
        let footnotesFound = false;

        paragrafos.forEach(paragrafo => {
            if (paragrafo.ingles.some(palavra => palavra.original.toLowerCase() === 'footnotes:')) {
                footnotesFound = true;
            }

            if (!footnotesFound) {
                paragrafo.ingles.forEach(palavra => {
                    if (palavra.is_composite) {
                        palavra.parts.forEach(part => {
                            if (!part.has_ipa && /[a-zA-Z]/.test(part.original.trim())) {
                                pendingMap.set(part.original.trim(), {
                                    ingles: part.original.trim(),
                                    frequency: (pendingMap.get(part.original.trim())?.frequency || 0) + 1
                                });
                            }
                        });
                    } else if (!palavra.has_ipa && /[a-zA-Z]/.test(palavra.original.trim())) {
                        pendingMap.set(palavra.original.trim(), {
                            ingles: palavra.original.trim(),
                            frequency: (pendingMap.get(palavra.original.trim())?.frequency || 0) + 1
                        });
                    }
                });
            }
        });

        this.pendingWords = Array.from(pendingMap.values());
        this.updatePendingCount();

        if (this.pendingWords.length > 0) {
            this.showPendingNotification();
        }
    }

    updatePendingCount() {
        const countElement = document.querySelector('.pending-count');
        if (countElement) {
            countElement.textContent = this.pendingWords.length;

            if (this.pendingWords.length > 0) {
                countElement.classList.add('has-pending');
            } else {
                countElement.classList.remove('has-pending');
            }
        }
    }

    showPendingNotification() {
        if (this.pendingWords.length > 0 && !localStorage.getItem('pending_notification_shown')) {
            this.app.notificationManager.show(
                `Encontradas ${this.pendingWords.length} palavras sem transcrição IPA. Clique em "Palavras Pendentes" para adicioná-las.`,
                'info'
            );
            localStorage.setItem('pending_notification_shown', 'true');
        }
    }

    togglePendingPanel() {
        const panel = document.querySelector('.pending-panel');
        if (panel.classList.contains('show')) {
            this.hidePendingPanel();
        } else {
            this.showPendingPanel();
        }
    }

    showPendingPanel() {
        const panel = document.querySelector('.pending-panel');
        panel.classList.add('show');
        this.renderPendingList();
    }

    hidePendingPanel() {
        const panel = document.querySelector('.pending-panel');
        panel.classList.remove('show');
    }

    renderPendingList() {
        const listContainer = document.querySelector('.pending-list');

        if (this.pendingWords.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-pending">
                    <i class="fas fa-check-circle"></i>
                    <p>Todas as palavras têm transcrição!</p>
                </div>
            `;
            return;
        }

        let html = '';
        this.pendingWords.forEach((word, index) => {
            html += `
                <div class="pending-item" data-index="${index}">
                    <label class="pending-checkbox">
                        <input type="checkbox" class="word-checkbox">
                        <span class="checkmark"></span>
                    </label>
                    <div class="pending-word">
                        <span class="word-text">${word.ingles}</span>
                        <span class="word-frequency">${word.frequency} ocorrência(s)</span>
                    </div>
                    <button class="edit-single-word" title="Editar esta palavra">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                </div>
            `;
        });

        listContainer.innerHTML = html;

        // Event listeners para os itens
        document.querySelectorAll('.edit-single-word').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = e.target.closest('.pending-item').dataset.index;
                this.editSingleWord(index);
            });
        });

        document.querySelectorAll('.word-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateSelectedCount();
            });
        });
    }

    filterWords(query) {
        const filtered = this.pendingWords.filter(word =>
            word.ingles.toLowerCase().includes(query.toLowerCase())
        );

        this.renderFilteredList(filtered);
    }

    renderFilteredList(words) {
        const listContainer = document.querySelector('.pending-list');

        if (words.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-pending">
                    <i class="fas fa-search"></i>
                    <p>Nenhuma palavra encontrada</p>
                </div>
            `;
            return;
        }

        let html = '';
        words.forEach((word, index) => {
            html += `
                <div class="pending-item">
                    <label class="pending-checkbox">
                        <input type="checkbox" class="word-checkbox">
                        <span class="checkmark"></span>
                    </label>
                    <div class="pending-word">
                        <span class="word-text">${word.ingles}</span>
                        <span class="word-frequency">${word.frequency} ocorrência(s)</span>
                    </div>
                    <button class="edit-single-word">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    sortWords(sortBy) {
        let sortedWords = [...this.pendingWords];

        switch (sortBy) {
            case 'alpha':
                sortedWords.sort((a, b) => a.ingles.localeCompare(b.ingles));
                break;
            case 'alpha-desc':
                sortedWords.sort((a, b) => b.ingles.localeCompare(a.ingles));
                break;
            case 'frequency':
                sortedWords.sort((a, b) => b.frequency - a.frequency);
                break;
        }

        this.renderFilteredList(sortedWords);
    }

    updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.word-checkbox:checked').length;
        const editSelectedBtn = document.querySelector('.btn-edit-selected');

        if (selectedCount > 0) {
            editSelectedBtn.disabled = false;
            editSelectedBtn.innerHTML = `<i class="fas fa-edit"></i> Editar ${selectedCount} Selecionada(s)`;
        } else {
            editSelectedBtn.disabled = true;
            editSelectedBtn.innerHTML = `<i class="fas fa-edit"></i> Editar Selecionadas`;
        }
    }

    getSelectedWords() {
        const selectedItems = [];
        document.querySelectorAll('.pending-item').forEach((item, index) => {
            const checkbox = item.querySelector('.word-checkbox');
            if (checkbox && checkbox.checked) {
                selectedItems.push(this.pendingWords[index]);
            }
        });
        return selectedItems;
    }

    editSelectedWords() {
        const selectedWords = this.getSelectedWords();
        if (selectedWords.length > 0) {
            this.submitToEditPage(selectedWords);
        }
    }

    editAllPendingWords() {
        this.submitToEditPage(this.pendingWords);
    }

    editSingleWord(index) {
        const word = this.pendingWords[index];
        if (word) {
            this.submitToEditPage([word]);
        }
    }

    submitToEditPage(words) {
        // Cria formulário oculto para enviar para adicionar.php
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'adicionar.php';
        form.style.display = 'none';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'palavras_pendentes';
        input.value = JSON.stringify(words.map(w => ({
            ingles: w.ingles,
            ipa: ''
        })));

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Inicialização da aplicação
document.addEventListener('DOMContentLoaded', async () => {
    window.bibliaIPA = new BibliaIPA();
    await window.bibliaIPA.init();
});