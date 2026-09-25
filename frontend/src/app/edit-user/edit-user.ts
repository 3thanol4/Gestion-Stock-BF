import { Component, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-edit-user',
  imports: [ReactiveFormsModule],
  templateUrl: './edit-user.html'
})
export class EditUserComponent implements OnInit {
  private authService = inject(AuthService);
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  editForm!: FormGroup;
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);
  isLoading = signal<boolean>(false);
  profileLoaded = signal<boolean>(false);
  targetUser = signal<any>(null);

  isPlatformAdmin = signal<boolean>(false);
  isEntrepriseAdmin = signal<boolean>(false);

  userId!: number;

  ngOnInit() {
    if (!this.authService.getToken()) {
      this.router.navigate(['/login']);
      return;
    }

    this.userId = +this.route.snapshot.params['id'];

    this.editForm = this.fb.group({
      name: ['', Validators.required],
      username: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      password: [''], // Vide = pas de changement
      role: ['', Validators.required],
      entreprise_nom: ['']
    });

    // Charger le profil courant puis l'utilisateur cible
    this.authService.fetchProfile().subscribe(currentUser => {
      if (currentUser && currentUser.roles) {
        const isPlatform = currentUser.roles.some((r: any) => r.name === 'administrateur_plateforme');
        const isEntreprise = currentUser.roles.some((r: any) => r.name === 'administrateur_entreprise');
        this.isPlatformAdmin.set(isPlatform);
        this.isEntrepriseAdmin.set(isEntreprise);
      }

      // Charger l'utilisateur cible
      this.authService.getUser(this.userId).subscribe({
        next: (user) => {
          this.targetUser.set(user);
          this.editForm.patchValue({
            name: user.name,
            username: user.username,
            email: user.email,
            role: user.roles?.[0]?.name || '',
            entreprise_nom: user.entreprise?.nom || ''
          });
          this.profileLoaded.set(true);
        },
        error: () => {
          this.errorMessage.set('Impossible de charger cet utilisateur.');
          this.profileLoaded.set(true);
        }
      });
    });
  }

  onSubmit() {
    if (this.editForm.valid) {
      this.isLoading.set(true);
      this.errorMessage.set(null);
      this.successMessage.set(null);

      const formData = { ...this.editForm.value };
      // Ne pas envoyer le mot de passe s'il est vide
      if (!formData.password) {
        delete formData.password;
      }

      this.authService.updateUser(this.userId, formData).subscribe({
        next: (res) => {
          this.isLoading.set(false);
          this.successMessage.set(res.message || 'Utilisateur mis à jour avec succès.');
        },
        error: (err) => {
          this.isLoading.set(false);
          if (err.error?.errors) {
            const firstError = Object.values(err.error.errors)[0] as string[];
            this.errorMessage.set(firstError[0]);
          } else if (err.error?.message) {
            this.errorMessage.set(err.error.message);
          } else {
            this.errorMessage.set('Erreur lors de la modification.');
          }
        }
      });
    }
  }

  goBack() {
    this.router.navigate(['/dashboard']);
  }
}
