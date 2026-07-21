<?php

namespace App\Modules\Hr\Recruitment\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Recruitment\Actions\AdvanceApplicationAction;
use App\Modules\Hr\Recruitment\Actions\CreateApplicationAction;
use App\Modules\Hr\Recruitment\Actions\CreateJobPostingAction;
use App\Modules\Hr\Recruitment\Actions\ManageJobOfferAction;
use App\Modules\Hr\Recruitment\Models\HrApplication;
use App\Modules\Hr\Recruitment\Models\HrJobOffer;
use App\Modules\Hr\Recruitment\Models\HrJobPosting;
use Livewire\Component;

class RecruitmentWorkspace extends Component
{
    public string $postingCode=''; public string $postingTitle=''; public int $headcount=1; public string $postingDescription=''; public string $postingClosesOn=''; public ?int $postingId=null; public string $candidateFirstName=''; public string $candidateLastName=''; public string $candidateEmail=''; public string $candidatePhone=''; public string $candidateSource=''; public string $candidateConsent='Adayın açık rıza beyanı kayıt altına alındı.'; public array $stageScores=[]; public array $stageNotes=[]; public array $rejectionReasons=[]; public array $offerSalary=[]; public array $offerStartDate=[]; public array $offerExpiresOn=[]; public array $offerApprovalNotes=[]; public string $postingFilter='';
    private const STAGES=['applied'=>'Başvuru','screening'=>'Ön Eleme','interview'=>'Görüşme','reference'=>'Referans','offer'=>'Teklif'];

    public function mount():void{$this->postingClosesOn=today()->addMonth()->toDateString();}
    public function createPosting(CreateJobPostingAction $action):void{$this->validate(['postingCode'=>'required|string|max:60','postingTitle'=>'required|string|max:180','headcount'=>'required|integer|min:1','postingClosesOn'=>'nullable|date']);$posting=$action->execute(['code'=>$this->postingCode,'title'=>$this->postingTitle,'headcount'=>$this->headcount,'description'=>$this->postingDescription?:null,'closes_on'=>$this->postingClosesOn?:null]);$this->postingId=$posting->id;$this->reset(['postingCode','postingTitle','postingDescription']);session()->flash('success','İlan taslağı oluşturuldu.');}
    public function publishPosting(int $id,CreateJobPostingAction $action):void{$action->publish($this->posting($id));session()->flash('success','İlan yayınlandı.');}
    public function addCandidate(CreateApplicationAction $action):void{$this->validate(['postingId'=>'required|integer','candidateFirstName'=>'required|string|max:100','candidateLastName'=>'required|string|max:100','candidateEmail'=>'required|email|max:180','candidateConsent'=>'required|string|max:1000']);$action->execute($this->posting($this->postingId),['first_name'=>$this->candidateFirstName,'last_name'=>$this->candidateLastName,'email'=>$this->candidateEmail,'phone'=>$this->candidatePhone?:null,'source'=>$this->candidateSource?:null,'consent_note'=>$this->candidateConsent]);$this->reset(['candidateFirstName','candidateLastName','candidateEmail','candidatePhone','candidateSource']);session()->flash('success','Aday başvurusu eklendi.');}
    public function advance(int $id,AdvanceApplicationAction $action):void{$application=$this->application($id);$keys=array_keys(self::STAGES);$index=array_search($application->current_stage,$keys,true);abort_if($index===false||!isset($keys[$index+1]),422);$score=$this->stageScores[$id]??null;$action->execute($application,$keys[$index+1],$score===''||$score===null?null:(float)$score,$this->stageNotes[$id]??null);unset($this->stageScores[$id],$this->stageNotes[$id]);}
    public function reject(int $id,AdvanceApplicationAction $action):void{$action->reject($this->application($id),(string)($this->rejectionReasons[$id]??''));unset($this->rejectionReasons[$id]);session()->flash('success','Başvuru insan kararı ve gerekçesiyle kapatıldı.');}
    public function createOffer(int $id,ManageJobOfferAction $action):void{$salary=$this->offerSalary[$id]??null;$start=$this->offerStartDate[$id]??null;$expires=$this->offerExpiresOn[$id]??null;abort_unless(is_numeric($salary)&&$start&&$expires,422,'Teklif tutarı ve tarihleri zorunludur.');$action->create($this->application($id),['gross_salary'=>$salary,'proposed_start_date'=>$start,'expires_on'=>$expires,'currency'=>'TRY']);session()->flash('success','Teklif ikinci onaya gönderildi.');}
    public function approveOffer(int $id,ManageJobOfferAction $action):void{$action->approve($this->offer($id),(string)($this->offerApprovalNotes[$id]??''));unset($this->offerApprovalNotes[$id]);session()->flash('success','Teklif insan onayıyla onaylandı.');}
    public function render(){ $tenant=app(TenantContext::class)->getId();$postings=HrJobPosting::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->withCount('applications')->latest()->get();$applications=HrApplication::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->when($this->postingFilter!=='',fn($q)=>$q->where('job_posting_id',$this->postingFilter))->with(['candidate','posting','offer','stages'])->latest()->get();return view('livewire.hr.recruitment.recruitment-workspace',['postings'=>$postings,'applications'=>$applications,'stageLabels'=>self::STAGES])->layout('layouts.app');}
    private function posting(?int $id):HrJobPosting{return HrJobPosting::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id);}private function application(int $id):HrApplication{return HrApplication::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id);}private function offer(int $id):HrJobOffer{return HrJobOffer::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id);}
}
