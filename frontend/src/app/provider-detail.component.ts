import {Component,inject,OnInit} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute,RouterLink} from '@angular/router';
import {HttpClient} from '@angular/common/http';

@Component({standalone:true,imports:[CommonModule,FormsModule,RouterLink],template:`
<section class="content provider-detail" *ngIf="provider; else state">
<a routerLink="/" class="back-link">← Voltar ao catálogo</a>
<div class="detail-hero" [style.backgroundImage]="provider.cover_image ? 'linear-gradient(90deg,#35101fe8,#35101f55),url('+provider.cover_image+')' : ''">
<div><span class="detail-kicker">FORNECEDOR LIRANDZU <b *ngIf="provider.verified">✓ VERIFICADO</b></span><h1>{{provider.business_name}}</h1><p>{{provider.city}}{{provider.province ? ', '+provider.province : ''}}{{provider.district ? ' · '+provider.district : ''}}</p></div>
</div>
<div class="detail-layout">
<main>
<section class="detail-card"><small>SOBRE O FORNECEDOR</small><h2>Conheça o trabalho</h2><p class="detail-description">{{provider.description}}</p><div class="chips"><span *ngFor="let c of provider.categories">{{c.name}}</span></div><p class="detail-price">{{provider.indicative_price ? (provider.indicative_price|number:'1.0-0')+' MZN' : 'Preço sob consulta'}}</p></section>
<section class="detail-card" *ngIf="provider.services?.length"><small>O QUE OFERECE</small><h2>Serviços e pacotes</h2><article class="service-item" *ngFor="let s of provider.services"><div><h3>{{s.name}}</h3><p>{{s.description}}</p></div><b>{{price(s)}}</b></article></section>
<section class="detail-card" *ngIf="provider.gallery?.length"><small>PORTFÓLIO</small><h2>Galeria de trabalhos</h2><div class="gallery-grid"><a *ngFor="let m of provider.gallery" [href]="m.media_url" target="_blank" rel="noopener noreferrer"><img *ngIf="m.media_type==='image'" [src]="m.media_url" [alt]="m.alt_text||provider.business_name"><span *ngIf="m.media_type==='video'">▶ Ver vídeo</span></a></div></section>
<section class="detail-card"><small>ENCONTRE O FORNECEDOR</small><h2>Vamos encaminhar o seu pedido</h2><p>A LIRANDZU recebe a sua solicitação e faz a intermediação. Os seus contactos não serão partilhados directamente com o fornecedor.</p><form (ngSubmit)="sendRequest()"><label>Nome completo<input name="name" [(ngModel)]="request.customer_name" required minlength="3"></label><label>Telefone<input name="phone" [(ngModel)]="request.customer_phone" required minlength="8"></label><label>E-mail (opcional)<input type="email" name="email" [(ngModel)]="request.customer_email"></label><label>Cidade do evento<input name="city" [(ngModel)]="request.event_city" required></label><label>Data do evento<input type="date" name="date" [(ngModel)]="request.event_date"></label><label>Orçamento indicativo (MZN)<input type="number" min="0" name="budget" [(ngModel)]="request.budget"></label><label class="full">Conte-nos o que procura<textarea name="message" [(ngModel)]="request.message" required minlength="10"></textarea></label><button [disabled]="sending">{{sending?'A enviar...':'Solicitar contacto através da LIRANDZU'}}</button></form><p class="detail-feedback">{{feedback}}</p><div class="reference" *ngIf="reference">Guarde esta referência para acompanhar o pedido: <b>{{reference}}</b></div></section>
</main>
<aside class="detail-card contact-card"><small>INTERMEDIAÇÃO LIRANDZU</small><h2>Um pedido, acompanhado por nós.</h2><p>Não precisa de procurar os contactos do fornecedor. A nossa equipa encaminha o seu pedido e acompanha a comunicação.</p><a *ngIf="provider.website_url" [href]="provider.website_url" target="_blank" rel="noopener noreferrer">Website</a><a *ngIf="provider.instagram_url" [href]="provider.instagram_url" target="_blank" rel="noopener noreferrer">Instagram</a><a *ngIf="provider.facebook_url" [href]="provider.facebook_url" target="_blank" rel="noopener noreferrer">Facebook</a></aside>
</div></section>
<ng-template #state><section class="content detail-state"><p *ngIf="loading">A carregar perfil do fornecedor...</p><div *ngIf="!loading"><h2>{{error||'Fornecedor não encontrado'}}</h2><a routerLink="/">Voltar ao catálogo</a></div></section></ng-template>
`}) export class ProviderDetailComponent implements OnInit {
private route=inject(ActivatedRoute);private http=inject(HttpClient);
provider:any=null;loading=true;error='';sending=false;feedback='';reference='';
request:any={customer_name:'',customer_phone:'',customer_email:'',event_city:'',event_date:'',budget:null,message:''};
ngOnInit(){const slug=this.route.snapshot.paramMap.get('slug');if(!slug){this.loading=false;this.error='Fornecedor não encontrado.';return;}this.http.get<any>('/api/providers/'+encodeURIComponent(slug)).subscribe({next:r=>{this.provider=r.data;this.loading=false;},error:e=>{this.error=e.error?.error||'Não foi possível carregar este perfil.';this.loading=false;}});}
price(s:any){if(s.price_unit==='quote'||(s.price_from===null&&s.price_to===null))return 'Sob consulta';const unit=({fixed:'',hour:' / hora',day:' / dia',guest:' / convidado',package:' / pacote'} as any)[s.price_unit]||'';if(s.price_from!==null&&s.price_to!==null)return Number(s.price_from).toLocaleString('pt-MZ')+'–'+Number(s.price_to).toLocaleString('pt-MZ')+' MZN'+unit;return Number(s.price_from??s.price_to).toLocaleString('pt-MZ')+' MZN'+unit;}
sendRequest(){if(this.sending||!this.provider)return;this.sending=true;this.feedback='';this.http.post<any>('/api/service-requests',{...this.request,provider_id:this.provider.id}).subscribe({next:r=>{this.reference=r.reference_code;this.feedback=r.message;this.sending=false;},error:e=>{this.feedback=e.error?.error||'Não foi possível enviar o pedido.';this.sending=false;}});}
}