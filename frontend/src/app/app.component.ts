import {Component, inject, OnInit} from '@angular/core';
import {CommonModule} from '@angular/common';
import {HttpClient} from '@angular/common/http';
import {NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {filter} from 'rxjs';

@Component({
 selector:'app-root',
 standalone:true,
 imports:[CommonModule,RouterLink,RouterLinkActive,RouterOutlet],
 template:`
 <header class="site-header">
  <a routerLink="/" class="brand" aria-label="LIRANDZU - página inicial"><b>LIRANDZU</b><small>LC CONSULTORIA</small></a>
  <nav aria-label="Navegação principal">
   <a routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{exact:true}">Explorar</a>
   <a routerLink="/candidatar-fornecedor" routerLinkActive="active">Seja fornecedor</a>
   <a *ngIf="user?.role==='admin'" routerLink="/admin">Administração</a>
   <a *ngIf="user?.role==='provider'" routerLink="/fornecedor">Área do fornecedor</a>
   <a *ngIf="!user" routerLink="/entrar" class="nav-login">Entrar</a>
   <button *ngIf="user" type="button" class="nav-logout" (click)="logout()">Sair</button>
  </nav>
 </header>
 <main><router-outlet/></main>
 <footer><b>LIRANDZU</b><span>LC CONSULTORIA · Moçambique</span><p>Ligamos histórias a fornecedores de confiança.</p><small>© {{year}} LIRANDZU. Todos os direitos reservados.</small></footer>
 `
})
export class AppComponent implements OnInit {
 private http=inject(HttpClient);
 private router=inject(Router);
 user:any=null;
 readonly year=new Date().getFullYear();
 ngOnInit(){
  this.http.get('/api/auth/csrf').subscribe({error:()=>{}});
  this.refreshUser();
  this.router.events.pipe(filter(event=>event instanceof NavigationEnd)).subscribe(()=>this.refreshUser());
 }
 private refreshUser(){this.http.get<any>('/api/auth/me').subscribe({next:r=>this.user=r.user,error:()=>this.user=null});}
 logout(){this.http.post('/api/auth/logout',{}).subscribe({next:()=>{this.user=null;this.router.navigateByUrl('/');},error:()=>{this.user=null;this.router.navigateByUrl('/');}});}
}
