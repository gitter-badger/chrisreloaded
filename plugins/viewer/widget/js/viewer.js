/**
 * This object takes care of all the visualization:
 *
 * FEATURES
 * - Give it a JSON file which represents the 'scene'
 * - Allows users to selects elements of the scene to be rendered
 * - Allows basic image processing (thresholding, Volume Rendering (VR), Fibers Length Thresholding, etc.)
 * - Expose some functions
 *
 * TECHNOLOGY
 * - XTK
 * - xdatgui.js
 * - SliceDrop clone
 */

// Declare (or re-declare) the single global variable
var viewer = viewer || {};


viewer.Viewer = function(jsonObj) {

  this.version = 0.0;
  //Parse the json file 
  this.source = jsonObj;
  
  //rendered volume 
  this.volume = null;
  this.sceneOrientation = 0;
  this.mode = 0;
  this.bbox = true;

  //rendered geometric models (eg. fibers and meshes) 
  this.geomModels = [];

  //file selection widget
  this.fileSelectWidget = null;
  this.createFileSelectTree('tree');

  // volume GUI widget
  this.volWidget = null;

  // try to create and initialize a 3D renderer
  this._webGLFriendly = true;
  try {
    this.create3DRenderer('33d');
  } catch (Exception) { 
    this._webGLFriendly = false;
  }
  // create the 2D renderers for the X, Y, Z orientations
  this.create2DRenderer('sliceXX', 'X');
  this.create2DRenderer('sliceYY', 'Y');
  this.create2DRenderer('sliceZZ', 'Z');

  // the onShowtime method gets executed after all files were fully loaded and
  // just before the first rendering attempt
  var self = this;
  this.sliceXX.onShowtime = function() {
    // add the volume to the other 3 renderers
    self.sliceYY.add(self.volume);
    self.sliceYY.render(); 
    self.sliceZZ.add(self.volume);
    self.sliceZZ.render();
    if (self._webGLFriendly) {
      self['33d'].add(self.volume);
      // .. and start the loading and rendering!
      self['33d'].camera.position = [0, 0, 200];
      self['33d'].render();
    } 
    // now the volume GUI widget
    if (!self.volWidget) {
      self.createVolWidget('xcontroller');
    } else {
      self.updateVolWidget();
    }
  };

  //Event handler for full screen behaviour main container is double clicked
  document.getElementById('33d').addEventListener('dblclick', function() {
    var render2D = document.getElementById('render2D');


    if (this.style.height == '100%') {
      render2D.style.display = 'block';
      this.style.height = '70%';
    } else {
      render2D.style.display = 'none';
      this.style.height = '100%'
    }
    var ev = document.createEvent('Event');
    ev.initEvent('resize', true, true);
    window.dispatchEvent(ev);
  });

  /*//Event handler for render button
  document.getElementById("renderbutton").addEventListener('click', function() {
    self.render();});*/
}


viewer.Viewer.prototype.create3DRenderer = function(container) {
  this[container] = new X.renderer3D();
  this[container].bgColor = [.1, .1, .1];
  this[container].container = container;
  this[container].init();
  // the volume and geometric models are not in the same space, so
  // we configure some transforms in the onShowtime method which gets executed
  // after all files were fully loaded and just before the first rendering
  // attempt
  this[container].onShowtime = function() {
   // $("#tree").fancytree("option", "disabled", false);
  // we reset the bounding box so track and mesh are in the same space
    this.resetBoundingBox();
    // (re)activate the tree picking
    // we have to do that to avoid race conditions/sync issues
  };
}


viewer.Viewer.prototype.create2DRenderer = function(container, orientation) {
  this[container] = new X.renderer2D();
  this[container].container = container;
  this[container].orientation = orientation;
  this[container].init();
}


viewer.Viewer.prototype.createFileSelectTree = function(container) {
  var self = this;

  $('#' + container).fancytree({
    checkbox: true,
    source: this.source,

    select: function(event, data) {

      // disable picking
      //$("#tree").fancytree("option", "disabled", true);

      var node = data.node;
      if (node.data.type == 'volume') {
        if (node.isSelected()) {
          if (self.volume != null) {
            var prevSelectedNode = self.fileSelectTree.getNodeByKey(self.volume.key);
            //uncheck previously selected volume node and call the select event
            prevSelectedNode.setSelected(false);
          }
          self.setVolume(node);
        } else {
          self.unsetVolume(node);
        }
      } else {
        if (node.isSelected()) {
          self.addGeomModel(node);
        } else {
          self.remGeomModel(node);
        }  
      };
    },

    keydown: function(event, data) {
      var node = data.node;
      if (event.which === 13) {
        if (node.isFolder()) {
          node.toggleExpanded();
        } else {
          node.toggleSelected();
        }
      }
    }     
  });

  this.fileSelectTree = $('#' + container).fancytree("getTree");
}


viewer.Viewer.prototype.setVolume = function(nodeObj) {
  var orderedFiles, files, url;

  url = nodeObj.data.url;

  // for the dicom format, files is a list of strings 
  // for other formats it's a list with just a single string 
  files = nodeObj.data.files;
  orderedFiles = files.sort().map(function(str) { 
      return url + '/' + str;});

  this.volume = new X.volume();
  this.volume.file = orderedFiles;
  this.volume.key = nodeObj.key;

  this.sliceXX.add(this.volume); 
  // start the loading/rendering
  this.sliceXX.render();
}


viewer.Viewer.prototype.unsetVolume = function(nodeObj) {
  // remove from the visualization
  if (this._webGLFriendly) {
    this['33d'].remove(this.volume);
  }

  this['sliceXX'].remove(this.volume);
  this['sliceYY'].remove(this.volume);
  this['sliceZZ'].remove(this.volume);

  this.volume = null;
}


viewer.Viewer.prototype.addGeomModel = function(nodeObj) {
  var xtkObj; 

  if (this._webGLFriendly && (this.indexOfGeomModel(nodeObj.key) == -1)) {
    xtkObj = new X[nodeObj.data.type]();
    xtkObj.file = nodeObj.data.url + '/' + nodeObj.data.files;
    xtkObj.key = nodeObj.key;
    this.geomModels.push(xtkObj);
    this['33d'].add(xtkObj);
    this['33d'].camera.position = [0, 0, 200];
    this['33d'].render();
  }
}


viewer.Viewer.prototype.remGeomModel = function(nodeObj) {
  var ix = this.indexOfGeomModel(nodeObj.key);

  if (ix != -1) {
    this['33d'].remove(this.geomModels[ix]);
    this.geomModels.splice(ix,1);
  }
}


viewer.Viewer.prototype.indexOfGeomModel = function(key) {
  var found = false;

  if (this.geomModels) {
    for (var i = 0; i < this.geomModels.length; i++) {
      if (this.geomModels[i].key == key) {
        return i;
     }
    }
  }
  if (!found) {
    return -1;
  }
}


viewer.Viewer.prototype.onThreshold = function() {

  window.console.log('Lets threshold!');
  //this.threeDRenderer 

}


viewer.Viewer.prototype.createVolWidget = function(container) {
  this.volWidget = {};
  var gui = new dat.GUI({ autoPlace: false });
  var customContainer = document.getElementById(container);
  customContainer.appendChild(gui.domElement);
  this.volWidget.view = gui.addFolder('View');
  // $('.interactive_plugin_content').css("background-color", "#000");
  // the following configures the gui for interacting with the X.volume
  this.volWidget.interact = gui.addFolder('Volume Interaction');
  this.populateVolWidget(); 
}

viewer.Viewer.prototype.populateVolWidget = function() {
  // now we can configure controllers ..
  //view mode
  this.volWidget.view.sliceMode = this.volWidget.view.add(this, 'mode', { 'Default':0, 'Rotate Box':1});
  this.volWidget.view.bboxMode = this.volWidget.view.add(this, 'bbox').name('Show BBox');
  this.volWidget.view.orientation = this.volWidget.view.add(this, 'sceneOrientation',
   { Free: 0, Sagittal: 1, Coronal: 2, Axial: 3 }).name('orientation');
  this.volWidget.view.open();
  // .. switch between slicing and volume rendering
  this.volWidget.interact.vrCtrl = this.volWidget.interact.add(this.volume, 'volumeRendering').name('rendering');
  // .. configure the volume rendering opacity
  this.volWidget.interact.opacityCtrl = this.volWidget.interact.add(this.volume, 'opacity', 0, 1);
  // .. and the threshold in the min..max range
  this.volWidget.interact.lowThCtrl = this.volWidget.interact.add(this.volume, 'lowerThreshold', 
    this.volume.min, this.volume.max).name('lowerThr');
  this.volWidget.interact.upThCtrl = this.volWidget.interact.add(this.volume, 'upperThreshold', 
    this.volume.min, this.volume.max).name('upperThr');
  this.volWidget.interact.lowWinCtrl = this.volWidget.interact.add(this.volume, 'windowLow', 
    this.volume.min, this.volume.max).name('winLow');
  this.volWidget.interact.upWinCtrl = this.volWidget.interact.add(this.volume, 'windowHigh',
   this.volume.min, this.volume.max).name('winHigh');
  // the indexX,Y,Z are the currently displayed slice indices in the range
  // 0..dimensions-1
  this.volWidget.interact.sliceXCtrl = this.volWidget.interact.add(this.volume, 'indexX', 0,
   this.volume.dimensions[0] - 1).listen();
  this.volWidget.interact.sliceYCtrl = this.volWidget.interact.add(this.volume, 'indexY', 0,
   this.volume.dimensions[1] - 1).listen();
  this.volWidget.interact.sliceZCtrl = this.volWidget.interact.add(this.volume, 'indexZ', 0,
   this.volume.dimensions[2] - 1).listen();
  this.volWidget.interact.open();
}


viewer.Viewer.prototype.updateVolWidget = function() {
  this.volWidget.view.remove(this.volWidget.view.sliceMode);
  this.volWidget.view.remove(this.volWidget.view.bboxMode);
  this.volWidget.view.remove(this.volWidget.view.orientation);
  this.volWidget.interact.remove(this.volWidget.interact.vrCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.opacityCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.lowThCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.upThCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.lowWinCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.upWinCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.sliceXCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.sliceYCtrl);
  this.volWidget.interact.remove(this.volWidget.interact.sliceZCtrl);
  this.populateVolWidget();
}
 
  /*   { title : '0001-1.3.12.2.1107.5.2.32.35162.2012021516003275873755302.dcm'
        url   : 'plugins/viewer/widget/data/dicom/',
        files : ['0001-1.3.12.2.1107.5.2.32.35162.2012021516003275873755302.dcm', 
                 '0002-1.3.12.2.1107.5.2.32.35162.2012021516003288462855318.dcm',
                 '0003-1.3.12.2.1107.5.2.32.35162.2012021516003360797655352.dcm',
                 '0004-1.3.12.2.1107.5.2.32.35162.2012021516003411054655384.dcm',
                 '0005-1.3.12.2.1107.5.2.32.35162.2012021516003465209455412.dcm'] }, 
      { url   : 'plugins/viewer/widget/data/', 
        files : ['recon.nii'] } ],
    fibers  : [
      { url   : 'plugins/viewer/widget/data/',
        files : ['tact.trk'] } ],
    models : [      
      { url   : 'plugins/viewer/widget/data/',
        files : ['lh.pial'] }, 
      { url   : 'plugins/viewer/widget/data/',
        files : ['rh.pial'] } ]; */


viewer.Viewer.prototype.viewChanged = function(arr){
    window.console.log('emit view changed');
}

// viewer.Viewer.prototype.viewEmitChanged = function(arr){
//     window.console.log('emit view changed');
//     self.viewChanged(viewM);
// }

viewer.Viewer.prototype.onViewChanged = function(arr){
    window.console.log('update view in view');
    window.console.log(this);
    this.threeD.camera.view = new Float32Array(arr);
}

viewer.Viewer.prototype.onTouchStart = function(){
    var self = this;
    _CHRIS_INTERACTIVE_PLUGIN_._updater = setInterval(function(){
            self.viewChanged(self.threeD.camera.view);
        }, 150);
}

viewer.Viewer.prototype.onTouchEnd = function(){
    clearInterval(_CHRIS_INTERACTIVE_PLUGIN_._updater);
}
