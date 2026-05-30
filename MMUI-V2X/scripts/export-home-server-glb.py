import bpy
from pathlib import Path

target = Path(r"D:\mui3\MMUI-V2X\public\models\home-server.glb")
target.parent.mkdir(parents=True, exist_ok=True)


def set_input(bsdf, name, value):
    if name in bsdf.inputs:
        bsdf.inputs[name].default_value = value


def make_material(
    name,
    base,
    roughness=0.82,
    emission=None,
    emission_strength=0.0,
    alpha=1.0,
    transmission=0.0,
    ior=1.35,
    coat=0.0,
    coat_roughness=0.3,
    metallic=0.0,
):
    material = bpy.data.materials.new(name)
    material.diffuse_color = base
    material.use_nodes = True
    bsdf = next((node for node in material.node_tree.nodes if node.type == "BSDF_PRINCIPLED"), None)
    if bsdf:
        set_input(bsdf, "Base Color", base)
        set_input(bsdf, "Alpha", alpha)
        set_input(bsdf, "Metallic", metallic)
        set_input(bsdf, "Roughness", roughness)
        set_input(bsdf, "IOR", ior)
        set_input(bsdf, "Transmission Weight", transmission)
        set_input(bsdf, "Coat Weight", coat)
        set_input(bsdf, "Coat Roughness", coat_roughness)
        if emission:
            set_input(bsdf, "Emission Color", emission)
            set_input(bsdf, "Emission Strength", emission_strength)

    if alpha < 1.0 or transmission > 0:
        material.blend_method = "BLEND"
        material.use_screen_refraction = True
        material.show_transparent_back = False
        material.alpha_threshold = 0.02

    return material


materials = {
    "main_white": make_material(
        "MMUI cloud porcelain",
        (0.88, 0.935, 0.995, 1.0),
        roughness=0.44,
        emission=(0.48, 0.62, 0.92, 1.0),
        emission_strength=0.006,
        coat=0.55,
        coat_roughness=0.26,
    ),
    "soft_white": make_material(
        "MMUI cloud frost",
        (0.78, 0.875, 0.98, 1.0),
        roughness=0.5,
        emission=(0.28, 0.46, 0.78, 1.0),
        emission_strength=0.006,
        coat=0.34,
        coat_roughness=0.34,
    ),
    "base_blue": make_material(
        "MMUI cloud base ice",
        (0.62, 0.76, 0.94, 1.0),
        roughness=0.48,
        emission=(0.16, 0.32, 0.68, 1.0),
        emission_strength=0.006,
        coat=0.34,
        coat_roughness=0.32,
    ),
    "accent_blue": make_material(
        "MMUI enterprise azure",
        (0.06, 0.2, 0.68, 1.0),
        roughness=0.28,
        emission=(0.0, 0.06, 0.28, 1.0),
        emission_strength=0.018,
        coat=0.58,
        coat_roughness=0.18,
    ),
    "glass": make_material(
        "MMUI cloud glass core",
        (0.36, 0.78, 1.0, 0.5),
        roughness=0.02,
        emission=(0.0, 0.42, 0.9, 1.0),
        emission_strength=0.18,
        alpha=0.5,
        transmission=0.86,
        ior=1.16,
        coat=0.45,
        coat_roughness=0.08,
    ),
    "top_glass": make_material(
        "MMUI azure glass tile",
        (0.04, 0.16, 0.72, 0.7),
        roughness=0.04,
        emission=(0.0, 0.08, 0.48, 1.0),
        emission_strength=0.08,
        alpha=0.7,
        transmission=0.45,
        ior=1.18,
        coat=0.55,
        coat_roughness=0.1,
    ),
    "yellow_led": make_material(
        "MMUI warm yellow LED",
        (1.0, 0.78, 0.28, 1.0),
        roughness=0.36,
        emission=(1.0, 0.74, 0.22, 1.0),
        emission_strength=0.75,
    ),
    "orange_led": make_material(
        "MMUI warm orange LED",
        (1.0, 0.48, 0.28, 1.0),
        roughness=0.36,
        emission=(1.0, 0.34, 0.16, 1.0),
        emission_strength=0.78,
    ),
}

assignment = {
    "立方体": "base_blue",
    "立方体.001": "soft_white",
    "立方体.002": "glass",
    "立方体.003": "main_white",
    "立方体.004": "accent_blue",
    "立方体.005": "main_white",
    "立方体.006": "glass",
    "立方体.007": "top_glass",
    "立方体.009": "main_white",
}

for obj in bpy.context.scene.objects:
    obj.hide_viewport = False
    obj.hide_render = False
    obj.select_set(obj.type == "MESH")

    if obj.type != "MESH":
        continue

    obj.data.materials.clear()
    if "柱体" in obj.name:
        obj.data.materials.append(materials["orange_led"] if obj.name.endswith(".002") else materials["yellow_led"])
    else:
        obj.data.materials.append(materials[assignment.get(obj.name, "main_white")])

    for polygon in obj.data.polygons:
        polygon.use_smooth = True

    if not any(modifier.type == "WEIGHTED_NORMAL" for modifier in obj.modifiers):
        obj.modifiers.new(name="MMUI weighted normals", type="WEIGHTED_NORMAL")

bpy.ops.export_scene.gltf(
    filepath=str(target),
    export_format="GLB",
    use_selection=True,
    export_apply=True,
    export_materials="EXPORT",
    export_cameras=False,
    export_lights=False,
    export_yup=True,
)

print(f"Exported -> {target}")
