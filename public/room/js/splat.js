// Gaussian splat viewer: three.js + Spark (World Labs), both bundled in /vendor.
import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { SparkRenderer, SplatMesh } from "@sparkjsdev/spark";

const EXT_TYPES = { ply: "ply", spz: "spz", splat: "splat", ksplat: "ksplat", sog: "pcsogszip" };

export function createSplatViewer(host, { onStatus = () => {} } = {}) {
  const renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.domElement.setAttribute("aria-label", "3D view of the room. Drag to orbit, scroll or pinch to zoom.");
  host.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(55, 1, 0.01, 200);
  scene.add(new SparkRenderer({ renderer }));

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.08;
  controls.minDistance = 0.15;
  controls.maxDistance = 12;
  controls.zoomToCursor = true;

  let mesh = null;
  let home = null;

  function resize() {
    const { width, height } = host.getBoundingClientRect();
    if (!width || !height) return;
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  }
  new ResizeObserver(resize).observe(host);
  resize();

  // Only render while the viewer is on screen.
  let running = false;
  const loop = () => { controls.update(); renderer.render(scene, camera); };
  new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting && !running) { renderer.setAnimationLoop(loop); running = true; }
    if (!entry.isIntersecting && running) { renderer.setAnimationLoop(null); running = false; }
  }).observe(host);

  function setView(position, target) {
    camera.position.set(...position);
    controls.target.set(...target);
    controls.update();
    home = { position: [...position], target: [...target] };
  }

  function frameToMesh() {
    const box = mesh.getBoundingBox(true).applyMatrix4(mesh.matrixWorld);
    const centre = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3()).length();
    setView([centre.x + size * 0.35, centre.y + size * 0.2, centre.z + size * 0.6], centre.toArray());
  }

  async function load({ url, bytes, fileName, fileType, flip = false, up = null, view }) {
    onStatus("loading");
    const next = new SplatMesh(bytes ? { fileBytes: bytes, fileName, fileType } : { url, fileType });
    try {
      await next.initialized;
    } catch (err) {
      next.dispose?.();
      onStatus("error", err);
      return false;
    }
    if (mesh) { scene.remove(mesh); mesh.dispose?.(); }
    mesh = next;
    if (flip) mesh.rotateX(Math.PI);
    if (up) mesh.quaternion.setFromUnitVectors(new THREE.Vector3(...up).normalize(), new THREE.Vector3(0, 1, 0));
    scene.add(mesh);
    mesh.updateMatrixWorld(true);
    if (view) setView(view.position, view.target); else frameToMesh();
    onStatus("ready");
    return true;
  }

  async function loadFile(file) {
    const ext = file.name.split(".").pop().toLowerCase();
    const fileType = EXT_TYPES[ext];
    if (!fileType) { onStatus("unsupported", ext); return false; }
    const bytes = new Uint8Array(await file.arrayBuffer());
    return load({ bytes, fileName: file.name, fileType });
  }

  return {
    load,
    loadFile,
    setView,
    getMesh: () => mesh,
    flip() { if (mesh) { mesh.rotateX(Math.PI); mesh.updateMatrixWorld(true); frameToMesh(); } },
    reset() { if (home) setView(home.position, home.target); },
  };
}

export function webgl2Available() {
  try { return !!document.createElement("canvas").getContext("webgl2"); } catch { return false; }
}
