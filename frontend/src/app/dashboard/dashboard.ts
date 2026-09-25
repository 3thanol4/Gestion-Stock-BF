import { Component, OnInit, inject, signal, DestroyRef } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-dashboard',
  imports: [FormsModule],
  templateUrl: './dashboard.html'
})
export class DashboardComponent implements OnInit {
  private authService = inject(AuthService);
  private router = inject(Router);
  private destroyRef = inject(DestroyRef);

  currentUser = signal<any>(null);
  isPlatformAdmin = signal<boolean>(false);
  /** Admin d’une entreprise (pas seulement plateforme). */
  isEntrepriseAdmin = signal<boolean>(false);
  isAdmin = signal<boolean>(false);
  canEditCatalog = signal<boolean>(false);
  canManageStock = signal<boolean>(false);
  canSell = signal<boolean>(false);

  // Navigation (onglet par défaut selon le rôle principal)
  activePage = signal<string>('articles');

  // Sélecteur entreprise (admin plateforme)
  entreprises = signal<any[]>([]);
  selectedEntrepriseId = signal<number | null>(null);

  // === DONNÉES ===
  // Utilisateurs
  users = signal<any[]>([]);

  // Articles
  articles = signal<any[]>([]);
  categories = signal<any[]>([]);
  showArticleForm = signal<boolean>(false);
  editingArticle = signal<any>(null);
  articleForm = { nom: '', code: '', unite_mesure: '', categorie_id: null as number | null };
  showCategorieForm = signal<boolean>(false);
  editingCategorie = signal<any>(null);
  categorieForm = { nom: '', code: '' };

  // Livraisons
  livraisons = signal<any[]>([]);
  showLivraisonForm = signal<boolean>(false);
  livraisonModalError = signal<string | null>(null);
  livraisonQuickCategorieNom = '';
  livraisonQuickArticle = { nom: '', unite_mesure: '', categorie_id: null as number | null };
  livraisonForm = {
    date_livraison: new Date().toISOString().split('T')[0],
    fournisseur_nom: '',
    fournisseur_contact: '',
    details: [{ article_id: null as number | null, quantite: 0, prix_achat_unitaire: 0, prix_vente_unitaire: 0 }]
  };

  // Ventes
  ventes = signal<any[]>([]);
  showVenteForm = signal<boolean>(false);
  venteForm = {
    date_vente: new Date().toISOString().split('T')[0],
    client_nom: '',
    client_contact: '',
    details: [{ article_id: null as number | null, quantite: 0, prix_vente_unitaire: 0, remise: 0 }]
  };

  // Reporting financier
  financialState = signal<any | null>(null);
  isLoadingFinancialState = signal<boolean>(false);
  selectedFinancialPeriod = signal<'journalier' | 'hebdomadaire' | 'mensuel' | 'annuel'>('journalier');

  // Feedback
  successMsg = signal<string | null>(null);
  errorMsg = signal<string | null>(null);

  // Loading / saving
  isLoadingPageData = signal<boolean>(false);
  isSavingLivraison = signal<boolean>(false);
  isSavingVente = signal<boolean>(false);

  // Recherche + pagination
  articlesQuery = signal<string>('');
  categoriesQuery = signal<string>('');
  livraisonsQuery = signal<string>('');
  ventesQuery = signal<string>('');

  // Sections pliables (utile surtout sur mobile)
  isCategoriesCollapsed = signal<boolean>(false);
  isArticlesCollapsed = signal<boolean>(false);

  articlesPage = signal<number>(1);
  livraisonsPage = signal<number>(1);
  ventesPage = signal<number>(1);
  pageSize = 10;

  private isSmallScreen(): boolean {
    if (typeof window === 'undefined') return false;
    return window.matchMedia?.('(max-width: 639px)')?.matches ?? false;
  }

  toggleCategoriesSection() {
    const next = !this.isCategoriesCollapsed();
    this.isCategoriesCollapsed.set(next);
    if (this.isSmallScreen() && !next) {
      this.isArticlesCollapsed.set(true);
    }
  }

  toggleArticlesSection() {
    const next = !this.isArticlesCollapsed();
    this.isArticlesCollapsed.set(next);
    if (this.isSmallScreen() && !next) {
      this.isCategoriesCollapsed.set(true);
    }
  }

  ngOnInit() {
    if (!this.authService.getToken()) {
      this.router.navigate(['/login']);
      return;
    }

    this.authService.fetchProfile().subscribe(user => {
      if (!user) return;
      this.currentUser.set(user);
      const roles = user.roles?.map((r: any) => r.name) || [];
      const isPlatform = roles.includes('administrateur_plateforme');
      const isEntAdmin = roles.includes('administrateur_entreprise');
      const isGest = roles.includes('gestionnaire_stock');
      const isVendeur = roles.includes('vendeur');

      this.isPlatformAdmin.set(isPlatform);
      this.isEntrepriseAdmin.set(isEntAdmin);
      this.isAdmin.set(isPlatform || isEntAdmin);
      this.canEditCatalog.set(isPlatform || isEntAdmin);
      this.canManageStock.set(isPlatform || isEntAdmin || isGest);
      this.canSell.set(isPlatform || isEntAdmin || isVendeur);

      let defaultTab = 'articles';
      if (isVendeur && !isPlatform && !isEntAdmin) {
        defaultTab = 'ventes';
      } else if (isGest && !isPlatform && !isEntAdmin) {
        defaultTab = 'stock';
      }
      this.activePage.set(defaultTab);

      if (isPlatform || isEntAdmin) {
        this.authService.getUsers().subscribe(u => this.users.set(u));
      }

      if (isPlatform) {
        this.authService.getEntreprises().subscribe(list => {
          this.entreprises.set(list);
          if (list.length && this.selectedEntrepriseId() === null) {
            const id = list[0].id;
            this.selectedEntrepriseId.set(id);
            this.authService.setSelectedEntreprise(id);
          }
          this.loadPageData();
        });
      } else if (user.entreprise_id) {
        this.selectedEntrepriseId.set(user.entreprise_id);
        this.authService.setSelectedEntreprise(user.entreprise_id);
        this.loadPageData();
      }

      if (typeof document !== 'undefined') {
        const onVis = () => {
          if (document.visibilityState !== 'visible') return;
          if (this.activePage() !== 'stock') return;
          if (!this.selectedEntrepriseId()) return;
          this.reloadArticlesFromApi();
        };
        document.addEventListener('visibilitychange', onVis);
        this.destroyRef.onDestroy(() => document.removeEventListener('visibilitychange', onVis));
      }
    });
  }

  onEntrepriseChange(id: string) {
    const numId = id ? Number.parseInt(id, 10) : null
    this.selectedEntrepriseId.set(numId);
    this.authService.setSelectedEntreprise(numId);
    this.loadPageData();
  }

  setPage(page: string) {
    this.activePage.set(page);
    this.clearMessages();
    this.resetPagingForPage(page);
    this.loadPageData();
  }

  loadPageData() {
    const page = this.activePage();
    if (page === 'entreprises' && this.isPlatformAdmin()) {
      this.authService.getEntreprises().subscribe(list => this.entreprises.set(list));
      return;
    }

    if (!this.selectedEntrepriseId() && !this.isPlatformAdmin()) return;
    if (!this.selectedEntrepriseId()) return;

    this.isLoadingPageData.set(true);

    if (page === 'articles') {
      this.authService.getCategories().subscribe({
        next: (c) => this.categories.set(c),
        error: (e) => this.errorMsg.set(this.formatHttpError(e))
      });
      this.authService.getArticles().subscribe({
        next: (a) => this.articles.set(a),
        error: (e) => this.errorMsg.set(this.formatHttpError(e)),
        complete: () => this.isLoadingPageData.set(false)
      });
    } else if (page === 'stock') {
      this.authService.getCategories().subscribe({
        next: (c) => this.categories.set(c),
        error: (e) => this.errorMsg.set(this.formatHttpError(e))
      });
      this.authService.getArticles().subscribe({
        next: (a) => this.articles.set(a),
        error: (e) => this.errorMsg.set(this.formatHttpError(e))
      });
      this.authService.getLivraisons().subscribe({
        next: (l) => this.livraisons.set(l),
        error: (e) => this.errorMsg.set(this.formatHttpError(e)),
        complete: () => this.isLoadingPageData.set(false)
      });
    } else if (page === 'ventes') {
      this.authService.getArticles().subscribe({
        next: (a) => this.articles.set(a),
        error: (e) => this.errorMsg.set(this.formatHttpError(e))
      });
      this.authService.getVentes().subscribe({
        next: (v) => this.ventes.set(v),
        error: (e) => this.errorMsg.set(this.formatHttpError(e)),
        complete: () => this.isLoadingPageData.set(false)
      });
    } else if (page === 'etat') {
      this.isLoadingPageData.set(false);
      this.loadFinancialState();
    } else if (page === 'users') {
      this.authService.getUsers().subscribe({
        next: (u) => this.users.set(u),
        error: (e) => this.errorMsg.set(this.formatHttpError(e)),
        complete: () => this.isLoadingPageData.set(false)
      });
    } else {
      this.isLoadingPageData.set(false);
    }
  }

  clearMessages() { this.successMsg.set(null); this.errorMsg.set(null); }

  /** Quantités : entiers sans « .000 », décimaux avec virgule (fr-FR). */
  fmtQty(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    const raw = typeof value === 'string'
      ? value.replaceAll(/\s/g, '').replaceAll(',', '.')
      : value;
    const n = Number(raw);
    if (Number.isNaN(n)) return String(value);
    const rounded3 = Math.round(n * 1000) / 1000;
    if (Math.abs(rounded3 - Math.round(rounded3)) < 0.0001) {
      return Math.round(rounded3).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
    }
    return rounded3.toLocaleString('fr-FR', { maximumFractionDigits: 3, useGrouping: false });
  }

  /** Montants : virgule décimale, sans « .00 » inutile sur les entiers. */
  fmtMoney(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    const raw = typeof value === 'string'
      ? value.replaceAll(/\s/g, '').replaceAll(',', '.')
      : value;
    const n = Number(raw);
    if (Number.isNaN(n)) return String(value);
    const x = Math.round(n * 100) / 100;
    if (Math.abs(x - Math.round(x)) < 0.001) {
      return Math.round(x).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
    }
    return x.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /** Recharge les articles (stock à l’écran) après vente / livraison / annulation ou bouton Actualiser. */
  reloadArticlesFromApi(): void {
    if (!this.selectedEntrepriseId()) return;
    this.authService.getArticles().subscribe(a => this.articles.set(a));
  }

  refreshStockView(): void {
    this.clearMessages();
    this.successMsg.set('Données actualisées.');
    this.loadPageData();
  }

  resetPagingForPage(page: string): void {
    if (page === 'articles') {
      this.articlesPage.set(1);
    } else if (page === 'stock') {
      this.livraisonsPage.set(1);
    } else if (page === 'ventes') {
      this.ventesPage.set(1);
    }
  }

  private normalizeForSearch(v: unknown): string {
    if (v === null || v === undefined) return '';
    return String(v).toLowerCase().trim();
  }

  // --------- Filtrage / pagination (listes) ---------
  getFilteredCategories(): any[] {
    const q = this.normalizeForSearch(this.categoriesQuery());
    if (!q) return this.categories();
    return this.categories().filter((c: any) => {
      return this.normalizeForSearch(c?.nom).includes(q)
        || this.normalizeForSearch(c?.code).includes(q);
    });
  }

  getFilteredArticles(): any[] {
    const q = this.normalizeForSearch(this.articlesQuery());
    if (!q) return this.articles();
    return this.articles().filter((a: any) => {
      return this.normalizeForSearch(a?.nom).includes(q)
        || this.normalizeForSearch(a?.code).includes(q)
        || this.normalizeForSearch(a?.categorie?.nom).includes(q);
    });
  }

  getPagedArticles(): any[] {
    const items = this.getFilteredArticles();
    const start = (this.articlesPage() - 1) * this.pageSize;
    return items.slice(start, start + this.pageSize);
  }

  getArticlesPageCount(): number {
    return Math.max(1, Math.ceil(this.getFilteredArticles().length / this.pageSize));
  }

  goArticlesPage(delta: number): void {
    const next = this.articlesPage() + delta;
    const max = this.getArticlesPageCount();
    this.articlesPage.set(Math.min(max, Math.max(1, next)));
  }

  getFilteredLivraisons(): any[] {
    const q = this.normalizeForSearch(this.livraisonsQuery());
    if (!q) return this.livraisons();
    return this.livraisons().filter((l: any) => {
      const hay = [l?.code, l?.date_livraison, l?.fournisseur_nom, l?.fournisseur_contact]
        .map((x: any) => this.normalizeForSearch(x))
        .join(' ');
      return hay.includes(q);
    });
  }

  getPagedLivraisons(): any[] {
    const items = this.getFilteredLivraisons();
    const start = (this.livraisonsPage() - 1) * this.pageSize;
    return items.slice(start, start + this.pageSize);
  }

  getLivraisonsPageCount(): number {
    return Math.max(1, Math.ceil(this.getFilteredLivraisons().length / this.pageSize));
  }

  goLivraisonsPage(delta: number): void {
    const next = this.livraisonsPage() + delta;
    const max = this.getLivraisonsPageCount();
    this.livraisonsPage.set(Math.min(max, Math.max(1, next)));
  }

  getFilteredVentes(): any[] {
    const q = this.normalizeForSearch(this.ventesQuery());
    if (!q) return this.ventes();
    return this.ventes().filter((v: any) => {
      const hay = [v?.code, v?.date_vente, v?.client_nom, v?.client_contact, v?.statut]
        .map((x: any) => this.normalizeForSearch(x))
        .join(' ');
      return hay.includes(q);
    });
  }

  getPagedVentes(): any[] {
    const items = this.getFilteredVentes();
    const start = (this.ventesPage() - 1) * this.pageSize;
    return items.slice(start, start + this.pageSize);
  }

  getVentesPageCount(): number {
    return Math.max(1, Math.ceil(this.getFilteredVentes().length / this.pageSize));
  }

  goVentesPage(delta: number): void {
    const next = this.ventesPage() + delta;
    const max = this.getVentesPageCount();
    this.ventesPage.set(Math.min(max, Math.max(1, next)));
  }

  loadFinancialState(): void {
    this.isLoadingFinancialState.set(true);
    this.authService.getFinancialState().subscribe({
      next: (data) => {
        this.financialState.set(data);
        this.isLoadingFinancialState.set(false);
      },
      error: (e) => {
        this.errorMsg.set(this.formatHttpError(e));
        this.isLoadingFinancialState.set(false);
      }
    });
  }

  setFinancialPeriod(period: 'journalier' | 'hebdomadaire' | 'mensuel' | 'annuel'): void {
    this.selectedFinancialPeriod.set(period);
  }

  getPeriodData(period: 'journalier' | 'hebdomadaire' | 'mensuel' | 'annuel'): any | null {
    return this.financialState()?.periodes?.[period] ?? null;
  }

  getPeriodLabel(period: 'journalier' | 'hebdomadaire' | 'mensuel' | 'annuel'): string {
    if (period === 'journalier') return 'Journalier';
    if (period === 'hebdomadaire') return 'Hebdomadaire';
    if (period === 'mensuel') return 'Mensuel';
    return 'Annuel';
  }

  private formatHttpError(err: any): string {
    if (err?.error?.message) return err.error.message;
    const errors = err?.error?.errors;
    if (errors && typeof errors === 'object') {
      const lines: string[] = [];
      for (const key of Object.keys(errors)) {
        const v = errors[key];
        if (Array.isArray(v)) lines.push(...v);
        else if (typeof v === 'string') lines.push(v);
      }
      if (lines.length) return lines.join(' ');
    }
    return 'Une erreur est survenue.';
  }

  // ───────── CATÉGORIES ─────────
  openCategorieForm(cat?: any) {
    this.editingCategorie.set(cat || null);
    this.categorieForm.nom = cat?.nom || '';
    this.categorieForm.code = cat?.code || '';
    this.showCategorieForm.set(true);
  }

  saveCategorie() {
    this.clearMessages();
    const data: any = { nom: this.categorieForm.nom };
    if (this.categorieForm.code?.trim()) {
      data.code = this.categorieForm.code.trim();
    }
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }

    const editing = this.editingCategorie();
    const obs = editing
      ? this.authService.updateCategorie(editing.id, data)
      : this.authService.createCategorie(data);

    obs.subscribe({
      next: () => {
        this.successMsg.set(editing ? 'Catégorie modifiée' : 'Catégorie créée');
        this.showCategorieForm.set(false);
        this.authService.getCategories().subscribe(c => this.categories.set(c));
      },
      error: (e) => this.errorMsg.set(e.error?.message || 'Erreur')
    });
  }

  deleteCategorie(id: number) {
    if (!confirm('Supprimer cette catégorie ?')) return;
    this.authService.deleteCategorie(id).subscribe({
      next: () => {
        this.successMsg.set('Catégorie supprimée');
        this.authService.getCategories().subscribe(c => this.categories.set(c));
      },
      error: (e) => this.errorMsg.set(e.error?.message || 'Erreur')
    });
  }

  // ───────── ARTICLES ─────────
  openArticleForm(article?: any) {
    this.editingArticle.set(article || null);
    this.articleForm.nom = article?.nom || '';
    this.articleForm.code = article?.code || '';
    this.articleForm.unite_mesure = article?.unite_mesure || '';
    this.articleForm.categorie_id = article?.categorie_id || null;
    this.showArticleForm.set(true);
  }

  saveArticle() {
    this.clearMessages();
    const data: any = {
      nom: this.articleForm.nom,
      unite_mesure: this.articleForm.unite_mesure,
      categorie_id: this.articleForm.categorie_id
    };
    if (this.articleForm.code?.trim()) {
      data.code = this.articleForm.code.trim();
    }
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }

    const editing = this.editingArticle();
    const obs = editing
      ? this.authService.updateArticle(editing.id, data)
      : this.authService.createArticle(data);

    obs.subscribe({
      next: () => {
        this.successMsg.set(editing ? 'Article modifié' : 'Article créé');
        this.showArticleForm.set(false);
        this.authService.getArticles().subscribe(a => this.articles.set(a));
        this.authService.getCategories().subscribe(c => this.categories.set(c));
      },
      error: (e) => this.errorMsg.set(this.formatHttpError(e))
    });
  }

  deleteArticle(id: number) {
    if (!confirm('Supprimer cet article ?')) return;
    this.authService.deleteArticle(id).subscribe({
      next: () => {
        this.successMsg.set('Article supprimé');
        this.authService.getArticles().subscribe(a => this.articles.set(a));
      },
      error: (e) => this.errorMsg.set(e.error?.message || 'Erreur')
    });
  }

  // ───────── LIVRAISONS ─────────
  openLivraisonForm() {
    this.livraisonModalError.set(null);
    this.livraisonQuickCategorieNom = '';
    this.livraisonQuickArticle = { nom: '', unite_mesure: '', categorie_id: null };
    this.livraisonForm = {
      date_livraison: new Date().toISOString().split('T')[0],
      fournisseur_nom: '', fournisseur_contact: '',
      details: [{ article_id: null, quantite: 0, prix_achat_unitaire: 0, prix_vente_unitaire: 0 }]
    };
    this.authService.getCategories().subscribe(c => this.categories.set(c));
    this.authService.getArticles().subscribe(a => this.articles.set(a));
    this.showLivraisonForm.set(true);
  }

  addCategorieFromLivraisonModal() {
    this.livraisonModalError.set(null);
    const nom = this.livraisonQuickCategorieNom.trim();
    if (!nom) {
      this.livraisonModalError.set('Indiquez un nom de catégorie (ex. Quincaillerie, Fournitures).');
      return;
    }
    const data: any = { nom };
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }
    this.authService.createCategorie(data).subscribe({
      next: (cat: any) => {
        this.livraisonQuickCategorieNom = '';
        this.authService.getCategories().subscribe(c => this.categories.set(c));
        this.livraisonQuickArticle.categorie_id = cat.id;
      },
      error: (e) => this.livraisonModalError.set(this.formatHttpError(e))
    });
  }

  addArticleFromLivraisonModal() {
    this.livraisonModalError.set(null);
    const nom = this.livraisonQuickArticle.nom.trim();
    const unite = this.livraisonQuickArticle.unite_mesure.trim();
    const cid = this.livraisonQuickArticle.categorie_id;
    if (!nom) {
      this.livraisonModalError.set('Indiquez le nom du produit (ex. Paquet de pointes).');
      return;
    }
    if (!unite) {
      this.livraisonModalError.set('Indiquez l’unité (ex. pièce, paquet, rouleau).');
      return;
    }
    if (!cid) {
      this.livraisonModalError.set('Choisissez une catégorie, ou créez-en une avec le champ au-dessus.');
      return;
    }
    const data: any = { nom, unite_mesure: unite, categorie_id: cid };
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }
    this.authService.createArticle(data).subscribe({
      next: (art: any) => {
        this.livraisonQuickArticle = { nom: '', unite_mesure: '', categorie_id: cid };
        this.authService.getArticles().subscribe(a => this.articles.set(a));
        const line = this.livraisonForm.details.find(d => d.article_id == null);
        if (line) line.article_id = art.id;
        else if (this.livraisonForm.details.length) {
          this.livraisonForm.details[0].article_id = art.id;
        }
      },
      error: (e) => this.livraisonModalError.set(this.formatHttpError(e))
    });
  }

  addLivraisonLine() {
    this.livraisonForm.details.push({ article_id: null, quantite: 0, prix_achat_unitaire: 0, prix_vente_unitaire: 0 });
  }

  removeLivraisonLine(i: number) {
    if (this.livraisonForm.details.length > 1) this.livraisonForm.details.splice(i, 1);
  }

  /** Gestionnaire : fenêtre article séparée (onglet Stock). */
  openStockManagerArticleForm() {
    this.clearMessages();
    this.authService.getCategories().subscribe(c => this.categories.set(c));
    this.openArticleForm();
  }

  openStockManagerCategorieForm() {
    this.clearMessages();
    this.authService.getCategories().subscribe(c => this.categories.set(c));
    this.openCategorieForm();
  }

  saveLivraison() {
    this.clearMessages();
    this.livraisonModalError.set(null);

    if (this.isSavingLivraison()) return;

    if (!this.livraisonForm.fournisseur_nom?.trim()) {
      this.livraisonModalError.set('Le nom du fournisseur est obligatoire.');
      return;
    }

    for (let i = 0; i < this.livraisonForm.details.length; i++) {
      const line = this.livraisonForm.details[i];
      if (line.article_id == null) {
        this.livraisonModalError.set(`Ligne ${i + 1} : choisissez un article dans la liste, ou créez-le avec « Ajouter au catalogue ».`);
        return;
      }
      const q = Number(line.quantite);
      if (!q || q <= 0) {
        this.livraisonModalError.set(`Ligne ${i + 1} : la quantité doit être supérieure à 0.`);
        return;
      }
      const pa = Number(line.prix_achat_unitaire);
      const pv = Number(line.prix_vente_unitaire);
      if (Number.isNaN(pa) || pa < 0 || Number.isNaN(pv) || pv < 0) {
        this.livraisonModalError.set(`Ligne ${i + 1} : renseignez des prix d’achat et de vente valides (≥ 0).`);
        return;
      }
    }

    const data: any = { ...this.livraisonForm };
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }

    this.isSavingLivraison.set(true);
    this.authService.createLivraison(data).subscribe({
      next: () => {
        this.successMsg.set('Livraison enregistrée — stock mis à jour');
        this.showLivraisonForm.set(false);
        this.reloadArticlesFromApi();
        this.loadPageData();
      },
      error: (e) => this.livraisonModalError.set(this.formatHttpError(e)),
      complete: () => this.isSavingLivraison.set(false)
    });
  }

  // ───────── VENTES ─────────
  openVenteForm() {
    this.venteForm = {
      date_vente: new Date().toISOString().split('T')[0],
      client_nom: '', client_contact: '',
      details: [{ article_id: null, quantite: 0, prix_vente_unitaire: 0, remise: 0 }]
    };
    this.reloadArticlesFromApi();
    this.showVenteForm.set(true);
  }

  addVenteLine() {
    this.venteForm.details.push({ article_id: null, quantite: 0, prix_vente_unitaire: 0, remise: 0 });
  }

  removeVenteLine(i: number) {
    if (this.venteForm.details.length > 1) this.venteForm.details.splice(i, 1);
  }

  onVenteArticleChange(detail: any) {
    const article = this.articles().find((a: any) => a.id == detail.article_id);
    if (article) {
      detail.prix_vente_unitaire = article.dernier_prix_vente || 0;
    }
  }

  getArticleStock(articleId: number | null | undefined): number {
    if (!articleId) return 0;
    const art = this.articles().find((a: any) => a.id == articleId);
    const n = Number(art?.quantite_stock ?? 0);
    return Number.isFinite(n) ? n : 0;
  }

  getVenteLineRemainingStock(line: any): number {
    const stock = this.getArticleStock(line?.article_id);
    const qty = Number(line?.quantite ?? 0);
    const q = Number.isFinite(qty) ? qty : 0;
    return stock - q;
  }

  private parseNumber(v: unknown): number {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return v;
    const s = String(v).replaceAll(/\s/g, '').replaceAll(',', '.');
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  getVenteMontantTotal(): number {
    return this.venteForm.details.reduce((sum, d) => {
      return sum + ((d.prix_vente_unitaire - (d.remise || 0)) * d.quantite);
    }, 0);
  }

  saveVente() {
    this.clearMessages();
    if (this.isSavingVente()) return;

    if (!this.venteForm.client_nom?.trim()) {
      this.errorMsg.set('Le nom du client est obligatoire.');
      return;
    }

    for (let i = 0; i < this.venteForm.details.length; i++) {
      const line = this.venteForm.details[i];
      if (line.article_id == null) {
        this.errorMsg.set(`Ligne ${i + 1} : choisissez un article.`);
        return;
      }
      const q = this.parseNumber(line.quantite);
      if (!q || q <= 0) {
        this.errorMsg.set(`Ligne ${i + 1} : la quantité doit être supérieure à 0.`);
        return;
      }

      const stock = this.getArticleStock(line.article_id);
      if (q > stock) {
        this.errorMsg.set(`Ligne ${i + 1} : stock insuffisant (disponible : ${this.fmtQty(stock)}).`);
        return;
      }

      const prix = this.parseNumber(line.prix_vente_unitaire);
      const remise = this.parseNumber(line.remise);
      if (prix < 0 || remise < 0) {
        this.errorMsg.set(`Ligne ${i + 1} : prix et remise doivent être ≥ 0.`);
        return;
      }
      if (remise > prix) {
        this.errorMsg.set(`Ligne ${i + 1} : remise > prix de vente.`);
        return;
      }
    }

    const total = this.getVenteMontantTotal();
    if (total < 0) {
      this.errorMsg.set('Le total de la vente ne peut pas être négatif.');
      return;
    }

    const data: any = { ...this.venteForm };
    if (this.isPlatformAdmin() && this.selectedEntrepriseId()) {
      data.entreprise_id = this.selectedEntrepriseId();
    }

    this.isSavingVente.set(true);
    this.authService.createVente(data).subscribe({
      next: () => {
        this.successMsg.set('Vente enregistrée — stock mis à jour côté serveur');
        this.showVenteForm.set(false);
        this.reloadArticlesFromApi();
        this.loadPageData();
      },
      error: (e) => {
        const msg = e.error?.errors?.stock?.[0] || e.error?.message || this.formatHttpError(e);
        this.errorMsg.set(msg);
      },
      complete: () => this.isSavingVente.set(false)
    });
  }

  annulerVente(id: number) {
    if (!confirm('Annuler cette vente ? Le stock sera restauré.')) return;
    this.clearMessages();
    this.authService.annulerVente(id).subscribe({
      next: (res) => {
        this.successMsg.set(res.message);
        this.reloadArticlesFromApi();
        this.loadPageData();
      },
      error: (e) => this.errorMsg.set(e.error?.message || 'Erreur')
    });
  }

  // ───────── UTILISATEURS ─────────
  toggleStatut(userId: number) {
    this.authService.toggleStatut(userId).subscribe({
      next: () => {
        if (this.isAdmin()) {
          this.authService.getUsers().subscribe(u => this.users.set(u));
        }
        this.loadPageData();
      },
      error: (e) => alert(e.error?.message || 'Erreur')
    });
  }

  editUser(userId: number) {
    this.router.navigate(['/edit-user', userId]);
  }

  goToRegister() {
    this.router.navigate(['/register']);
  }

  // ───────── UTILITAIRES ─────────
  getRoleName(user: any): string {
    if (!user?.roles?.length) return 'Aucun';
    const map: any = {
      'administrateur_plateforme': 'Admin Plateforme',
      'administrateur_entreprise': 'Admin Entreprise',
      'gestionnaire_stock': 'Gestionnaire Stock',
      'vendeur': 'Vendeur'
    };
    return map[user.roles[0].name] || user.roles[0].name;
  }

  logout() {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  // --------- Totaux Livraisons (modal) ---------
  getLivraisonTotals(): { totalAchat: number; totalVente: number; marge: number } {
    const totalAchat = this.livraisonForm.details.reduce((sum, d: any) => {
      return sum + (this.parseNumber(d.quantite) * this.parseNumber(d.prix_achat_unitaire));
    }, 0);
    const totalVente = this.livraisonForm.details.reduce((sum, d: any) => {
      return sum + (this.parseNumber(d.quantite) * this.parseNumber(d.prix_vente_unitaire));
    }, 0);
    return {
      totalAchat,
      totalVente,
      marge: totalVente - totalAchat
    };
  }
}
