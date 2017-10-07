<?php

namespace App\Http\Controllers;

use App\Libraries\listGenerates;
use App\Models\Content\Portlet;
use App\Models\Content\Portlet_page;
use App\Models\Content\Content;
use App\Models\Content\Structure;
use App\Models\Content\Page;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Requests;
use Illuminate\Http\Response;
use Validator;
use Exception;
use Illuminate\Validation\Rule;
use App\Repositories\RepositoryInterface;
use App\Libraries\Theme;
use Illuminate\Support\Facades\Log;
use App\Libraries\position;
use App\Libraries\Helpers;

class pageController extends Controller {

    private $rp;

    public function __construct(RepositoryInterface $rp)  {
        $this->middleware('auth');
        $this->rp = $rp->setModel('App\Models\Content\Page')->setSearchFields(['name','description']);
    }

    /**
     * @param array $data
     * @param bool $onUpdate
     * @return \Illuminate\Validation\Validator
     */
    private function validator(array $data,$onUpdate=false)   {
        $filter = 'unique:pages';
        if ($onUpdate) { $filter = Rule::unique('pages')->ignore($data['id']);}
        return Validator::make($data, [
            'name' => 'sometimes|required|min:3|max:255',
            //'slug' => ['sometimes','min:2','max:100','regex:/^[a-z0-9-.]+$/',$filter]
        ]);
    }

    /**
     * Visualizza la lista delle pagine, eventualmente filtrata
     * @param Request $request
     * @param listGenerates $list
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request, listGenerates $list) {
        $pages = $this->rp->paginate($request);
        $list->setModel($pages);
        return view('content.listPage')->with(compact('pages','list'));
    }

    /**
     * Mostra il form per la creazione della pagina
     * @return \Illuminate\Contracts\View\View
     */
    public function create(Theme $theme,$id=null)   {
        $page = new Page(); $action = "PageController@store";
        if ($id) {
            $optionsSel = $this->rp->where('id',$id)->pluck()->toArray();
        } else {
            $optionsSel = $this->rp->optionsSel();
        }
        $listThemes = $theme->listThemes();
        return view('content.editPage')->with(compact('page','action','optionsSel','listThemes'));
    }

    /**
     * Salva la pagina nel database dopo aver validato i dati
     * @param Request $request
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)   {
        $data = $request->all();
        $this->validator($data)->validate();
        $data['user_id'] = \Auth::user()->id; $data['username'] = \Auth::user()->username;
        $data['parent_id'] = $data['parent_id'] ?: null;
        $data['theme'] = (!empty($data['theme'])) ? $data['theme'] : config('newportal.theme-default');
        $this->rp->create($data);
        return redirect()->route('pages')->withSuccess('Pagina creata correttamente.');
    }

    /**
     * Mostra il form per l'aggiornamento della pagina
     * @param $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit($id, Theme $theme) {
        $page = $this->rp->find($id);
        $optionsSel = $this->rp->optionsSel($id);
        $listThemes = $theme->listThemes();
        //$listLayouts = $theme->setTheme($page->theme)->listlayouts();
        $action = ["PageController@update",$id];
        return view('content.editPage')->with(compact('page','action','optionsSel','listThemes'));
    }

    /**
     * Aggiorna i dati nel DB
     * @param $id
     * @param Request $request
     * @return $this
     */
    public function update($id, Request $request)  {
        $data = $request->all();
        $data['id'] = $id;
        if (!$request->has('hidden_')) $data['hidden_'] = 0;
        $this->validator($data,true)->validate();
        if (isset($data['parent_id'])) $data['parent_id'] = $data['parent_id'] ?: null;
        if ($this->rp->update($id,$data)) {
            return redirect()->route('pages')->withSuccess('Pagina aggiornata correttamente');
        }
        return redirect()->back()->withErrors('Si è verificato un  errore');
    }

    /**
     * Cancella la pagina - chiede conferma prima della cancellazione
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)  {
        if ($this->rp->delete($id)) {
            return redirect()->back()->withSuccess('pagina cancellata correttamente');
        }
        return redirect()->back();
    }

    /**
     * duplica la pagina, impostando tutte le portlets
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function duplicates($id, Helpers $helpers) {
        $page = $this->rp->find($id);
        $clone = $page->replicate();
        $clone->name = $page->name."-".$helpers->makeCode();
        $clone->slug = str_slug($clone->name);
        $clone->save();
        foreach($page->portlets as $portlet) {
            $otherField = array_except($portlet->pivot->toArray(),
                ['id',"created_at","updated_at","page_id"]);
            $this->rp->attach($clone->portlets(),$portlet,$otherField);
        }
        return redirect()->back();
    }

    /**
     * Mostra il profilo della pagina
     * @param $Id
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function profile($Id, Request $request) {
        $page = $this->rp->find($Id);
        $pag['nexid'] = $this->rp->next($Id);
        $pag['preid'] = $this->rp->prev($Id);
        $listChildren = new listGenerates($this->rp->paginateArray($this->listChildren($Id)->toArray(),10,$request->page_a,'page_a'));
        $graphPage = $this->rp->whereNull('parent_id')->get();
        $titleGraph = "Rappresentazione grafica delle pagine";
        return view('content.profilePage')->with(compact('page','listChildren','pag','titleGraph','graphPage'));
    }

    /**
     * Restituisce la lista delle pagine children
     * @param $id
     * @return mixed
     */
    public function listChildren($id)  {
        return $this->rp->find($id)->children;
    }

    /**
     * Elimina il parent dalla pagina figlia
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delChild($id)  {
        $child = $this->rp->find($id);
        $this->rp->dissociate($child->parent());
        return redirect()->back();
    }

    /**
     * Mostra le liste delle portlet disponibili, di quelle agganciate alla pagina corrente
     * e dei frame presenti nel layout
     * @param $id
     * @param Theme $theme
     * @param int $current_key
     * @return mixed
     */
    public function layout($id, Theme $theme,$current_key=0) {

        $page = $this->rp->find($id);
        // valida alternativa a ->withPivot inserito nel model page
        // $page = Page::with('portlets')->find($id);
        $listLayouts = $theme->setTheme($page->theme)->listlayouts();
        array_unshift($listLayouts, "");
        $listFrames = $theme->listFramesOfLayout($page->layout);
        $keys = array_keys($listFrames);
        $current_key_index = array_search((int)$current_key, $keys, true);
        $frame['name'] = $frame['index'] = $frame['nexid'] = $frame['preid'] = null; $frame['pageId'] = $id;
        if ($current_key_index!==false) {
            $frame['name'] = $listFrames[$current_key_index];
            $count = count($listFrames);
            $frame['nexid'] = ($current_key_index==$count-1) ? 0 : $current_key_index + 1;
            $frame['preid'] = ($current_key_index==0) ? $count - 1: $current_key_index-1;
            $frame['index'] = $current_key_index;
        }
        $portlets = $page->portlets()->wherePivot('frame', $frame['name'])->orderBy('position')->get()->toArray();
        $listPortletsAssign = new listGenerates($this->rp->paginateArray($portlets,10,\Request::input('page_a'),'page_a'));
        $portletsArray = $this->rp->setModel(new Portlet())->all()->toArray();
        $listPortletsDisp = new listGenerates($this->rp->paginateArray($portletsArray,4,\Request::input('page_b'),'page_b'));

        $action = ["PageController@update",$id];
        return view('content.assignPortletFrame')->with(compact('page','action','listLayouts',
            'listPortletsAssign','listFrames','listPortletsDisp','frame'));
    }

    /**
     * Aggancia la portlet alla pagina
     * @param $idPage
     * @param $idportlet
     * @param $frame
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addPortlet($idPage, $idportlet, $frame ) {
        $page = $this->rp->find($idPage);
        $name = $this->rp->setModel(new Portlet())->find($idportlet)->name;
        $this->rp->attach($page->portlets(),$idportlet,['frame'=>$frame,'name'=>$name]);
        $this->order($frame,$idPage);
        return redirect()->back();
    }

    /**
     * Eliminazione della portlet dalla pagina
     * @param $idPage
     * @param $idPivot
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delPortlet($idPage, $idPivot) {
        $frame = $this->rp->setModel(new Portlet_page())->find($idPivot)->frame;
        $page = $this->rp->setModel('App\Models\Content\Page')->find($idPage);
        $this->rp->detach($page->resources(),$idPivot);
        $this->order($frame,$idPage);
        return redirect()->back();
    }

    /**
     * Aggiorna la pivot, salvando le preferenze della portlet rispetto ad una pagina
     * Poichè la pivot accetta più portlet con lo stesso id non è possibile usare
     * $page->portlets()->sync([idportlet => $db ], false); né
     * $page->portlets()->updateExistingPivot(idportlet, $db);
     * @param Request $request
     * @return null
     */
    public function savePref(Request $request) {
        //Log::info($request);
        if ($request->has('data')) {
            $data = json_decode($request->data, true);
            foreach ($data as $item) {

                if (str_contains($item['name'], 'categories')){
                    $newdata['categories'][] = ['category'=>$item['value']];
                } elseif (str_contains($item['name'], 'tags')){
                    $newdata['tags'][] = ['tag'=>$item['value']];
                } else {
                    $newdata[$item['name']] = $item['value'];
                }
            }

            $modelpp = $this->rp->setModel(new Portlet_page());
            $portpage = $modelpp->find($newdata['pivot_id']);
            $arrport = array('css','js','template','position','title','comunication'); $setting = $db = [];

            foreach (array_except($newdata, ['page_id','pivot_id']) as $key=>$val) {
                if (in_array($key,$arrport)) {
                    $db[$key] = $val;
                } else {
                    if (!empty($val)) {
                        $setting[$key] = $val;
                    }
                }
            }
            if (count($setting)>0) $db['setting'] = json_encode($setting,true);
            $modelpp->update($newdata['pivot_id'],$db);
            $this->order($portpage->frame,$newdata['page_id'],$newdata['pivot_id'],$portpage->position,$newdata['position']);
            $resp = ['success' => true];
            return response()->json($resp, 200);
            //Log::info($newdata);
        }
    }

    /**
     * Configurazione della portlet inserita nella pagina
     * @param $idPage
     * @param $idPivot
     * @param Theme $theme
     * @return mixed
     * @throws Exception
     */
    public function configPortlet($idPage, $idPivot, Theme $theme) {
        $page = $this->rp->setModel(Page::class)->find($idPage);
        $portlet = $page->portlets()->wherepivot('id', $idPivot)->first();
        $className = "App\\".config('newportal.portlets.namespace')."\\".$portlet->path."\\".$portlet->init;
        if (class_exists($className)) {
            $init = new $className($this->rp,$theme);
            return $init->configPortlet($portlet);
        } else {
            throw new Exception("Classe $className non trovata");
        }
    }

    public function getPref($id, Theme $theme) {
        $inpage =   $this->rp->setModel(new Portlet_page())->find($id);
        $elements =   $this->rp->where('page_id',$inpage->page_id)->where('frame',$inpage->frame)->count();
        $templates = [""=>""]+$theme->listPartials($this->rp->setModel('App\Models\Content\Page')->find($inpage->page_id)->theme);
        return json_encode( $inpage->toArray()+['numportlets'=>$elements,'templates'=>$templates] );
    }

    /**
     * Aggancia le portlet alla pagina o le aggiorna (chiamata js)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function savePortlets(Request $request) {
        if ($request->has('data')) {
            $data = json_decode($request->data, true);
            $pp = $this->rp->setModel(new Portlet_page());
            $NewPP = null;
            foreach ($data as $item) {
                if (!empty($item['portlet_id']) && !isset($item['pivot_id'])) {
                    $item['name'] = $this->rp->find($item['portlet_id'],new Portlet)->name;
                    //$page = $this->rp->setModel($p)->find($item['page_id']);
                    //attach($page->portlets(),$item['portlet_id'],['frame'=>$item['frame'],'position'=>$item['position']]);
                    $NewPP = $pp->create($item);
                } else {
                    $set['frame'] = $item['frame'];
                    $set['position'] = $item['position'];
                    $pp->update($item['pivot_id'],$set);
                }
            }
            // la chiamata può effettuare un solo insert
            $resp = ['success' => true];
            if ($NewPP) {
                $resp['last_id'] =$NewPP->id;
            }
            //Log::info($item['name']);
            return response()->json($resp, 200);
        }
    }

    /**
     * Ordina i record in base al valore di position
     * @param $frame
     * @param null $id
     * @param null $pos
     * @param null $newpos
     */
    private function order($frame,$page_id,$id=null,$pos=null,$newpos=null) {
        $this->rp->setModel(new Portlet_page());
        (new position($this->rp))->reorder($id,$pos,$newpos,['frame'=>$frame,'page_id'=>$page_id]);
    }

}
