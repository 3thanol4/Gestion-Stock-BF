import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { BehaviorSubject, Observable, of, tap, shareReplay } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  private tokenSubject = new BehaviorSubject<string | null>(this.getToken());
  public token$ = this.tokenSubject.asObservable();

  private currentUserSubject = new BehaviorSubject<any>(null);
  public currentUser$ = this.currentUserSubject.asObservable();
  private profileRequest$: Observable<any> | null = null;

  // Entreprise sélectionnée (pour admin plateforme)
  private selectedEntrepriseId = new BehaviorSubject<number | null>(null);
  public selectedEntrepriseId$ = this.selectedEntrepriseId.asObservable();

  constructor(private http: HttpClient) {
    if (this.getToken()) {
      this.fetchProfile().subscribe();
    }
  }

  private getAuthHeaders(): HttpHeaders {
    return new HttpHeaders({
      'Authorization': `Bearer ${this.getToken()}`,
      'Accept': 'application/json'
    });
  }

  private withEntrepriseParam(params?: HttpParams): HttpParams {
    let p = params || new HttpParams();
    const eid = this.selectedEntrepriseId.value;
    if (eid) {
      p = p.set('entreprise_id', eid.toString());
    }
    return p;
  }

  setSelectedEntreprise(id: number | null) {
    this.selectedEntrepriseId.next(id);
  }

  getSelectedEntreprise(): number | null {
    return this.selectedEntrepriseId.value;
  }

  login(username: string, password: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/token`, { username, password }, { headers: {'Accept': 'application/json'}})
      .pipe(
        tap(response => {
          if (response.token || response.access) {
            this.setToken(response.token || response.access);
            this.fetchProfile(true).subscribe();
          }
        })
      );
  }

  fetchProfile(forceRefresh = false): Observable<any> {
    const token = this.getToken();
    if (!token) {
      this.currentUserSubject.next(null);
      return of(null);
    }
    if (!forceRefresh && this.currentUserSubject.value) {
      return of(this.currentUserSubject.value);
    }
    if (!this.profileRequest$) {
      this.profileRequest$ = this.http.get<any>(`${this.apiUrl}/user`, {
        headers: this.getAuthHeaders()
      }).pipe(
        tap(user => {
          this.currentUserSubject.next(user);
          this.profileRequest$ = null;
          // Auto-sélectionner l'entreprise si admin entreprise
          if (user?.entreprise_id && !user.roles?.some((r: any) => r.name === 'administrateur_plateforme')) {
            this.selectedEntrepriseId.next(user.entreprise_id);
          }
        }),
        shareReplay(1)
      );
    }
    return this.profileRequest$;
  }

  getCurrentUser(): any {
    return this.currentUserSubject.value;
  }

  // ───────── Utilisateurs ─────────
  register(userData: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/users`, userData, { headers: this.getAuthHeaders() });
  }

  getUsers(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/users`, { headers: this.getAuthHeaders() });
  }

  getUser(userId: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/users/${userId}`, { headers: this.getAuthHeaders() });
  }

  updateUser(userId: number, data: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/users/${userId}`, data, { headers: this.getAuthHeaders() });
  }

  toggleStatut(userId: number): Observable<any> {
    return this.http.patch<any>(`${this.apiUrl}/users/${userId}/toggle-statut`, {}, { headers: this.getAuthHeaders() });
  }

  // ───────── Entreprises ─────────
  getEntreprises(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/entreprises`, { headers: this.getAuthHeaders() });
  }

  // ───────── Catégories ─────────
  getCategories(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/categories`, {
      headers: this.getAuthHeaders(),
      params: this.withEntrepriseParam()
    });
  }

  createCategorie(data: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/categories`, data, { headers: this.getAuthHeaders() });
  }

  updateCategorie(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/categories/${id}`, data, { headers: this.getAuthHeaders() });
  }

  deleteCategorie(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/categories/${id}`, { headers: this.getAuthHeaders() });
  }

  // ───────── Articles ─────────
  getArticles(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/articles`, {
      headers: this.getAuthHeaders(),
      params: this.withEntrepriseParam()
    });
  }

  createArticle(data: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/articles`, data, { headers: this.getAuthHeaders() });
  }

  updateArticle(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/articles/${id}`, data, { headers: this.getAuthHeaders() });
  }

  deleteArticle(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/articles/${id}`, { headers: this.getAuthHeaders() });
  }

  // ───────── Livraisons ─────────
  getLivraisons(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/livraisons`, {
      headers: this.getAuthHeaders(),
      params: this.withEntrepriseParam()
    });
  }

  createLivraison(data: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/livraisons`, data, { headers: this.getAuthHeaders() });
  }

  // ───────── Ventes ─────────
  getVentes(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/ventes`, {
      headers: this.getAuthHeaders(),
      params: this.withEntrepriseParam()
    });
  }

  createVente(data: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/ventes`, data, { headers: this.getAuthHeaders() });
  }

  annulerVente(id: number): Observable<any> {
    return this.http.patch<any>(`${this.apiUrl}/ventes/${id}/annuler`, {}, { headers: this.getAuthHeaders() });
  }

  // ───────── Reporting ─────────
  getFinancialState(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/reports/financial-state`, {
      headers: this.getAuthHeaders(),
      params: this.withEntrepriseParam()
    });
  }

  // ───────── Auth ─────────
  logout(): void {
    localStorage.removeItem('jwt_token');
    this.tokenSubject.next(null);
    this.currentUserSubject.next(null);
    this.selectedEntrepriseId.next(null);
    this.profileRequest$ = null;
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  private setToken(token: string): void {
    localStorage.setItem('jwt_token', token);
    this.tokenSubject.next(token);
  }

  public getToken(): string | null {
    return localStorage.getItem('jwt_token');
  }
}
