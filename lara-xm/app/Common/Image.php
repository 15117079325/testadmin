<?php  
/** 
 图片压缩操作类 
 v1.0 
*/  
   class Image{  
         
       private $src;  
       private $imageinfo;  
       private $image;  
       public  $percent = 0.1;  
       public function __construct($src){  
             
           $this->src = $src;  
             
       }  
       /** 
       打开图片 
       */  
       public function openImage(){  
             
           list($width, $height, $type, $attr) = getimagesize($this->src);  
           $this->imageinfo = array(  
                  
                'width'=>$width,  
                'height'=>$height,  
                'type'=>image_type_to_extension($type,false),  
                'attr'=>$attr  
           );  
           $fun = "imagecreatefrom".$this->imageinfo['type'];  //方法，创建一个新图像
           //  等价于imagecreatefromjpeg($this->src);如果type是jpeg  
           $this->image = $fun($this->src); 
           return $this->imageinfo; 
       }  
       /** 
       操作图片 
       */  
       public function thumpImage(){  
             
            $new_width = $this->imageinfo['width'] * $this->percent;  
            $new_height = $this->imageinfo['height'] * $this->percent; 
            // $new_width = $this->imageinfo['width'] * 0.5;
            // $new_height = $this->imageinfo['height'] * 0.8; 
             //返回一个图像标识符，代表了一幅大小为 x_size 和 y_size 的黑色图像。
            $image_thump = imagecreatetruecolor($new_width,$new_height);  
            //将原图复制带图片载体上面，并且按照一定比例压缩,极大的保持了清晰度  
            imagecopyresampled($image_thump,$this->image,0,0,0,0,$new_width,$new_height,$this->imageinfo['width'],$this->imageinfo['height']);  
            // imagedestroy() 释放与 image 关联的内存。image 是由图像创建函数返回的图像标识符，例如 imagecreatetruecolor()。
            imagedestroy($this->image);    
            $this->image =   $image_thump; 
            return $this->image; 
       }  
       /** 
       输出图片 
       */  
       public function showImage(){  
            ob_clean();
            header('Content-Type: image/'.$this->imageinfo['type']);  
            $funcs = "image".$this->imageinfo['type']; 
            // imagejpeg() 从 image 图像以 filename 为文件名创建一个 JPEG 图像。 
            $funcs($this->image);  
             
       }  
       /** 
       保存图片到硬盘 
       */  
       public function saveImage($name){  
             
            $funcs = "image".$this->imageinfo['type'];    
            $bol = $funcs($this->image,base_url().'upload/admimgs/'.$name.'.'.$this->imageinfo['type']); 
            if ($bol) {
                return '/upload/admimgs/'.$name.'.'.$this->imageinfo['type'];
            }else{
              echo 'fail';
            }
             
       }  
       /** 
       销毁图片 
       */  
       public function __destruct(){  
             
           imagedestroy($this->image);  
       }  
         
   }  
   
  
?>