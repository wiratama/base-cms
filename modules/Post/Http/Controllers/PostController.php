<?php
namespace Module\Post\Http\Controllers;

use Module\Main\Http\Repository\CrudRepository;
use Module\Main\Http\Controllers\AdminBaseController;
use Module\Post\Http\Skeleton\PostSkeleton;
use Module\Main\Transformer\Exportable;
use Module\Main\Contracts\WithRevision;
use PostInstance;
use Module\Main\Transformer\Seo;

class PostController extends AdminBaseController implements WithRevision
{
	use Exportable, Seo;
	public $hint = 'post';

	public function repo(){
		return $this->hint;
	}

	public function skeleton(){
		return new PostSkeleton;
	}

	public function image_field(){
		return ['image'];
	}


	public function detail($id){
		$data = $this->repo->show($id);
		if(empty($data)){
			abort(404);
		}
		$title = $data->title;
		$structure = PostInstance::setData($data)->structure();
		return view('post::detail', compact(
			'title',
			'data',
			'structure'
		));
	}

	public function comment($id){
		$data = $this->repo->show($id);
		if(empty($data)){
			abort(404);
		}

		$this->request->validate([
			'message' => 'required'
		], [
			'message.required' => 'Please fill the message'
		]);

		//karena ini route admin, jadi comment semuanya as admin ya..
		$pass = [
			'message' => $this->request->message
		];

		if($this->request->reply_to){
			$pass['reply_to'] = $this->request->reply_to;
		}

		PostInstance::setData($data)->comment($pass, true);
		return back()->with('success', 'Comment data has been saved.');
	}


	public function afterCrud($instance){
		$cats = $this->request->category;
		if(!$this->request->category){
			$cats = [];
		}
		$this->handleStoreCategory($instance, $cats);

		$rel = $this->request->related;
		if(!$this->request->related){
			$rel = [];
		}
		$this->handleStoreRelated($instance, $rel);
	}

	protected function handleStoreRelated($instance, $relateds=[]){
		$relmodel = app(config('model.post_related'));
		$check = $relmodel->where('post_id', $instance->id)->get();
		foreach($relateds as $rel){
			if($check->where('post_related_id', $instance->id)->count() == 0){
				$relmodel->insert([
					'post_id' => $instance->id,
					'post_related_id' => $rel
				]);
			}
		}

		if(!empty($relateds)){
			$relmodel->where('post_id', $instance->id)->whereNotIn('post_related_id', $relateds)->delete();
		}
		else{
			$relmodel->where('post_id', $instance->id)->delete();
		}
	}

	protected function handleStoreCategory($instance, $categories=[]){
		$catmodel = app(config('model.post_to_category'));
		$check = $catmodel->where('post_id', $instance->id)->get();
		foreach($categories as $cat){
			if($check->where('post_category_id', $cat)->count() == 0){
				$catmodel->insert([
					'post_id' => $instance->id,
					'post_category_id' => $cat
				]);
			}
		}

		if(!empty($categories)){
			$catmodel->where('post_id', $instance->id)->whereNotIn('post_category_id', $categories)->delete();
		}
		else{
			$catmodel->where('post_id', $instance->id)->delete();
		}
	}


	public function storeRevisionFormat($instance){
		$base = $instance->toArray();
		if($instance->category->count() > 0){
			foreach($instance->category as $cat){
				$base['category'][] = [
					'id' => $cat->id,
					'name' => $cat->name,
					'slug' => $cat->slug
				];
			}
		}
		
		if($instance->related->count() > 0){
			foreach($instance->related as $rel){
				$base['related'][] = [
					'id' => $rel->id,
					'title' => $rel->title,
					'slug' => $rel->slug
				];
			}
		}
		return $base;
	}

	//manage revision shown fields
	public function reformatRevision($data=[]){
		$cat = '';
		if(isset($data['category'])){
			foreach($data['category'] as $c){
				$cat .= '<span class="badge badge-primary mb-1">'.$c['name'].'</span> ';
			}
		}

		if(strlen($cat) == 0){
			$cat = '-';
		}
		else{
			$cat = '<div>'.$cat.'</div>';
		}


		$rel = '';
		if(isset($data['related'])){
			foreach($data['related'] as $c){
				$rel .= '<span class="badge badge-primary mb-1">'.$c['title'].'</span> ';
			}
		}

		if(strlen($rel) == 0){
			$rel = '-';
		}
		else{
			$rel = '<div>'.$rel.'</div>';
		}


		return [
			'id' => $data['id'],
			'Title' => $data['title'],
			'Description' => descriptionMaker($data['description'], 10),
			'Category' => $cat,
			'Related Post' => $rel,
			'Updated' => date('d M Y H:i:s', strtotime($data['updated_at']))
		];
	}

	//revision activate condition
	public function activateRevision($instance, $revision_data=[]){
		//dynamically restore revision data
		foreach($revision_data as $field => $value){
			if(in_array($field, ['id', 'created_at', 'updated_at', 'is_active']) || is_array($value)){
				continue;
			}
			$instance->{$field} = $value;
		}
		$instance->save();

		if(isset($revision_data['category'])){
			$cats = collect($revision_data['category'])->pluck('id')->toArray();
			$this->handleStoreCategory($instance, $cats);
		}
		if(isset($revision_data['related'])){
			$rels = collect($revision_data['related'])->pluck('id')->toArray();
			$this->handleStoreRelated($instance, $rels);
		}
	}


}